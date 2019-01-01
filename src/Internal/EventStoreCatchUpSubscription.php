<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Amp\Loop;
use Amp\Promise;
use Amp\Success;
use Closure;
use Generator;
use Prooph\EventStore\AsyncCatchUpSubscriptionDropped;
use Prooph\EventStore\AsyncEventStoreCatchUpSubscription;
use Prooph\EventStore\AsyncEventStoreConnection;
use Prooph\EventStore\CatchUpSubscriptionSettings;
use Prooph\EventStore\ClientConnectionEventArgs;
use Prooph\EventStore\EventAppearedOnAsyncCatchupSubscription;
use Prooph\EventStore\EventAppearedOnAsyncSubscription;
use Prooph\EventStore\EventStoreSubscription;
use Prooph\EventStore\ListenerHandler;
use Prooph\EventStore\LiveProcessingStartedOnAsyncCatchUpSubscription;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SubscriptionDropped;
use Prooph\EventStore\SubscriptionDropReason;
use Prooph\EventStore\UserCredentials;
use Psr\Log\LoggerInterface as Logger;
use SplQueue;
use Throwable;
use function Amp\call;

abstract class EventStoreCatchUpSubscription implements AsyncEventStoreCatchUpSubscription
{
    /** @var ResolvedEvent */
    private static $dropSubscriptionEvent;

    /** @var bool */
    private $isSubscribedToAll;
    /** @var string */
    private $streamId;
    /** @var string */
    private $subscriptionName;

    /** @var Logger */
    protected $log;

    /** @var AsyncEventStoreConnection */
    private $connection;
    /** @var bool */
    private $resolveLinkTos;
    /** @var UserCredentials|null */
    private $userCredentials;

    /** @var int */
    protected $readBatchSize;
    /** @var int */
    protected $maxPushQueueSize;

    /** @var EventAppearedOnAsyncCatchupSubscription */
    protected $eventAppeared;
    /** @var LiveProcessingStartedOnAsyncCatchUpSubscription|null */
    private $liveProcessingStarted;
    /** @var AsyncCatchUpSubscriptionDropped|null */
    private $subscriptionDropped;

    /** @var bool */
    protected $verbose;

    /** @var SplQueue<ResolvedEvent> */
    private $liveQueue;
    /** @var EventStoreSubscription|null */
    private $subscription;
    /** @var DropData|null */
    private $dropData;
    /** @var bool */
    private $allowProcessing;
    /** @var bool */
    private $isProcessing;
    /** @var bool */
    protected $shouldStop;
    /** @var bool */
    private $isDropped;
    /** @var ManualResetEventSlim */
    private $stopped;

    /** @var ListenerHandler */
    private $connectListener;

    /** @internal */
    public function __construct(
        AsyncEventStoreConnection $connection,
        Logger $logger,
        string $streamId,
        ?UserCredentials $userCredentials,
        EventAppearedOnAsyncCatchupSubscription $eventAppeared,
        ?LiveProcessingStartedOnAsyncCatchUpSubscription $liveProcessingStarted,
        ?AsyncCatchUpSubscriptionDropped $subscriptionDropped,
        CatchUpSubscriptionSettings $settings
    ) {
        if (null === self::$dropSubscriptionEvent) {
            self::$dropSubscriptionEvent = new ResolvedEvent(null, null, null);
        }

        $this->log = $logger;
        $this->connection = $connection;
        $this->isSubscribedToAll = empty($streamId);
        $this->streamId = $streamId;
        $this->userCredentials = $userCredentials;
        $this->eventAppeared = $eventAppeared;
        $this->liveProcessingStarted = $liveProcessingStarted;
        $this->subscriptionDropped = $subscriptionDropped;
        $this->resolveLinkTos = $settings->resolveLinkTos();
        $this->readBatchSize = $settings->readBatchSize();
        $this->maxPushQueueSize = $settings->maxLiveQueueSize();
        $this->verbose = $settings->verboseLogging();
        $this->liveQueue = new SplQueue();
        $this->subscriptionName = $settings->subscriptionName() ?? '';
        $this->connectListener = new ListenerHandler(function (): void {
        });
        $this->stopped = new ManualResetEventSlim(true);
    }

    public function isSubscribedToAll(): bool
    {
        return $this->isSubscribedToAll;
    }

    public function streamId(): string
    {
        return $this->streamId;
    }

    public function subscriptionName(): string
    {
        return $this->subscriptionName;
    }

    abstract protected function readEventsTillAsync(
        AsyncEventStoreConnection $connection,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials,
        ?int $lastCommitPosition,
        ?int $lastEventNumber
    ): Promise;

    abstract protected function tryProcessAsync(ResolvedEvent $e): Promise;

    /** @internal */
    public function startAsync(): Promise
    {
        if ($this->verbose) {
            $this->log->debug(\sprintf(
                'Catch-up Subscription %s to %s: starting...',
                $this->subscriptionName,
                $this->isSubscribedToAll ? '<all>' : $this->streamId
            ));
        }

        return $this->runSubscriptionAsync();
    }

    public function stop(?int $timeout = null): Promise
    {
        if ($this->verbose) {
            $this->log->debug(\sprintf(
                'Catch-up Subscription %s to %s: requesting stop...',
                $this->subscriptionName,
                $this->isSubscribedToAll ? '<all>' : $this->streamId
            ));
            $this->log->debug(\sprintf(
                'Catch-up Subscription %s to %s: unhooking from connection.Connected',
                $this->subscriptionName,
                $this->isSubscribedToAll ? '<all>' : $this->streamId
            ));
        }

        $this->connection->detach($this->connectListener);
        $this->shouldStop = true;
        $this->enqueueSubscriptionDropNotification(SubscriptionDropReason::userInitiated(), null);

        if (null === $timeout) {
            return new Success();
        }

        if ($this->verbose) {
            $this->log->debug(\sprintf(
                'Waiting on subscription %s to stop',
                $this->subscriptionName
            ));
        }

        return $this->stopped->wait($timeout);
    }

    private function onReconnect(ClientConnectionEventArgs $clientConnectionEventArgs): void
    {
        if ($this->verbose) {
            $this->log->debug(\sprintf(
                'Catch-up Subscription %s to %s: recovering after reconnection',
                $this->subscriptionName,
                $this->isSubscribedToAll ? '<all>' : $this->streamId
            ));
            $this->log->debug(\sprintf(
                'Catch-up Subscription %s to %s: unhooking from connection.Connected',
                $this->subscriptionName,
                $this->isSubscribedToAll ? '<all>' : $this->streamId
            ));
        }

        $this->connection->detach($this->connectListener);

        Loop::defer(function (): Generator {
            yield $this->runSubscriptionAsync();
        });
    }

    private function runSubscriptionAsync(): Promise
    {
        return $this->loadHistoricalEventsAsync();
    }

    private function loadHistoricalEventsAsync(): Promise
    {
        if ($this->verbose) {
            $this->log->debug(\sprintf(
                'Catch-up Subscription %s to %s: running...',
                $this->subscriptionName,
                $this->isSubscribedToAll ? '<all>' : $this->streamId
            ));
        }

        $this->stopped->reset();
        $this->allowProcessing = false;

        return call(function (): Generator {
            if (! $this->shouldStop) {
                if ($this->verbose) {
                    $this->log->debug(\sprintf(
                        'Catch-up Subscription %s to %s: pulling events...',
                        $this->subscriptionName,
                        $this->isSubscribedToAll ? '<all>' : $this->streamId
                    ));
                }

                try {
                    yield $this->readEventsTillAsync($this->connection, $this->resolveLinkTos, $this->userCredentials, null, null);
                    yield $this->subscribeToStreamAsync();
                } catch (Throwable $ex) {
                    $this->dropSubscription(SubscriptionDropReason::catchUpError(), $ex);

                    throw $ex;
                }
            } else {
                $this->dropSubscription(SubscriptionDropReason::userInitiated(), null);
            }

            return new Success($this);
        });
    }

    private function subscribeToStreamAsync(): Promise
    {
        return call(function (): Generator {
            if (! $this->shouldStop) {
                if ($this->verbose) {
                    $this->log->debug(\sprintf(
                        'Catch-up Subscription %s to %s: subscribing...',
                        $this->subscriptionName,
                        $this->isSubscribedToAll ? '<all>' : $this->streamId
                    ));
                }

                $eventAppeared = new class(Closure::fromCallable([$this, 'enqueuePushedEvent'])) implements EventAppearedOnAsyncSubscription {
                    private $callback;

                    public function __construct(callable $callback)
                    {
                        $this->callback = $callback;
                    }

                    public function __invoke(
                        EventStoreSubscription $subscription,
                        ResolvedEvent $resolvedEvent
                    ): Promise {
                        return ($this->callback)($subscription, $resolvedEvent);
                    }
                };

                $subscriptionDropped = new class(Closure::fromCallable([$this, 'serverSubscriptionDropped'])) implements SubscriptionDropped {
                    private $callback;

                    public function __construct(callable $callback)
                    {
                        $this->callback = $callback;
                    }

                    public function __invoke(
                        EventStoreSubscription $subscription,
                        SubscriptionDropReason $reason,
                        ?Throwable $exception = null
                    ): void {
                        ($this->callback)($reason, $exception);
                    }
                };

                $subscription = empty($this->streamId)
                    ? yield $this->connection->subscribeToAllAsync(
                        $this->resolveLinkTos,
                        $eventAppeared,
                        $subscriptionDropped,
                        $this->userCredentials
                    )
                    : yield $this->connection->subscribeToStreamAsync(
                        $this->streamId,
                        $this->resolveLinkTos,
                        $eventAppeared,
                        $subscriptionDropped,
                        $this->userCredentials
                    );

                $this->subscription = $subscription;

                yield $this->readMissedHistoricEventsAsync();
            } else {
                $this->dropSubscription(SubscriptionDropReason::userInitiated(), null);
            }
        });
    }

    private function readMissedHistoricEventsAsync(): Promise
    {
        return call(function (): Generator {
            if (! $this->shouldStop) {
                if ($this->verbose) {
                    $this->log->debug(\sprintf(
                    'Catch-up Subscription %s to %s: pulling events (if left)...',
                        $this->subscriptionName,
                        $this->isSubscribedToAll ? '<all>' : $this->streamId
                    ));
                }

                yield $this->readEventsTillAsync(
                    $this->connection,
                    $this->resolveLinkTos,
                    $this->userCredentials,
                    $this->subscription->lastCommitPosition(),
                    $this->subscription->lastEventNumber()
                );
                $this->startLiveProcessing();
            } else {
                $this->dropSubscription(SubscriptionDropReason::userInitiated(), null);
            }
        });
    }

    private function startLiveProcessing(): void
    {
        if ($this->shouldStop) {
            $this->dropSubscription(SubscriptionDropReason::userInitiated(), null);

            return;
        }

        if ($this->verbose) {
            $this->log->debug(\sprintf(
                'Catch-up Subscription %s to %s: processing live events...',
                $this->subscriptionName,
                $this->isSubscribedToAll ? '<all>' : $this->streamId
            ));
        }

        if ($this->liveProcessingStarted) {
            ($this->liveProcessingStarted)($this);
        }

        if ($this->verbose) {
            $this->log->debug(\sprintf(
                'Catch-up Subscription %s to %s: hooking to connection.Connected',
                $this->subscriptionName,
                $this->isSubscribedToAll ? '<all>' : $this->streamId
            ));
        }

        $this->connectListener = $this->connection->onConnected(function (ClientConnectionEventArgs $args): void {
            $this->onReconnect($args);
        });

        $this->allowProcessing = true;

        $this->ensureProcessingPushQueue();
    }

    private function enqueuePushedEvent(EventStoreSubscription $subscription, ResolvedEvent $e): Promise
    {
        if ($this->verbose) {
            $this->log->debug(\sprintf(
                'Catch-up Subscription %s to %s: event appeared (%s, %s, %s, @ %s)',
                $this->subscriptionName,
                $this->isSubscribedToAll ? '<all>' : $this->streamId,
                $e->originalStreamName(),
                $e->originalEventNumber(),
                $e->originalEvent()->eventType(),
                $e->originalPosition()
            ));
        }

        if ($this->liveQueue->count() >= $this->maxPushQueueSize) {
            $this->enqueueSubscriptionDropNotification(SubscriptionDropReason::processingQueueOverflow(), null);
            $subscription->unsubscribe();

            return new Success();
        }

        $this->liveQueue->enqueue($e);

        if ($this->allowProcessing) {
            $this->ensureProcessingPushQueue();
        }

        return new Success();
    }

    private function serverSubscriptionDropped(
        SubscriptionDropReason $reason,
        ?Throwable $exception): void
    {
        $this->enqueueSubscriptionDropNotification($reason, $exception);
    }

    private function enqueueSubscriptionDropNotification(SubscriptionDropReason $reason, ?Throwable $error): void
    {
        // if drop data was already set -- no need to enqueue drop again, somebody did that already
        $dropData = new DropData($reason, $error);

        if (null === $this->dropData) {
            $this->dropData = $dropData;

            $this->liveQueue->enqueue(self::$dropSubscriptionEvent);

            if ($this->allowProcessing) {
                $this->ensureProcessingPushQueue();
            }
        }
    }

    private function ensureProcessingPushQueue(): void
    {
        if (! $this->isProcessing) {
            $this->isProcessing = true;

            Loop::defer(function (): Generator {
                yield $this->processLiveQueueAsync();
            });
        }
    }

    private function processLiveQueueAsync(): Promise
    {
        return call(function (): Generator {
            do {
                while (! $this->liveQueue->isEmpty()) {
                    $e = $this->liveQueue->dequeue();
                    \assert($e instanceof ResolvedEvent);

                    if ($e === self::$dropSubscriptionEvent) {
                        $this->dropData = $this->dropData ?? new DropData(SubscriptionDropReason::unknown(), new \Exception('Drop reason not specified'));
                        $this->dropSubscription($this->dropData->reason(), $this->dropData->error());

                        $this->isProcessing = false;

                        return null;
                    }

                    try {
                        yield $this->tryProcessAsync($e);
                    } catch (Throwable $ex) {
                        $this->log->debug(\sprintf(
                            'Catch-up Subscription %s to %s: Exception occurred in subscription %s',
                            $this->subscriptionName,
                            $this->isSubscribedToAll ? '<all>' : $this->streamId,
                            $ex->getMessage()
                        ));

                        $this->dropSubscription(SubscriptionDropReason::eventHandlerException(), $ex);

                        return null;
                    }
                }
            } while ($this->liveQueue->count() > 0);

            $this->isProcessing = false;
        });
    }

    public function dropSubscription(SubscriptionDropReason $reason, ?Throwable $error): void
    {
        if (! $this->isDropped) {
            $this->isDropped = true;

            if ($this->verbose) {
                $this->log->debug(\sprintf(
                    'Catch-up Subscription %s to %s: dropped subscription, reason: %s %s',
                    $this->subscriptionName,
                    $this->isSubscribedToAll ? '<all>' : $this->streamId,
                    $reason->name(),
                    null === $error ? '' : $error->getMessage()
                ));
            }

            if ($this->subscription) {
                $this->subscription->unsubscribe();
            }

            if ($this->subscriptionDropped) {
                ($this->subscriptionDropped)($this, $reason, $error);
            }

            $this->stopped->set();
        }
    }
}
