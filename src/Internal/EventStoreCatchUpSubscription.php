<?php
/**
 * This file is part of the prooph/event-store-client.
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
use Prooph\EventStoreClient\CatchUpSubscriptionDropped;
use Prooph\EventStoreClient\CatchUpSubscriptionSettings;
use Prooph\EventStoreClient\ClientConnectionEventArgs;
use Prooph\EventStoreClient\EventAppearedOnCatchupSubscription;
use Prooph\EventStoreClient\EventAppearedOnSubscription;
use Prooph\EventStoreClient\EventStoreAsyncConnection;
use Prooph\EventStoreClient\EventStoreSubscription;
use Prooph\EventStoreClient\Exception\TimeoutException;
use Prooph\EventStoreClient\Internal\ResolvedEvent as InternalResolvedEvent;
use Prooph\EventStoreClient\LiveProcessingStarted;
use Prooph\EventStoreClient\ResolvedEvent;
use Prooph\EventStoreClient\SubscriptionDropped;
use Prooph\EventStoreClient\SubscriptionDropReason;
use Prooph\EventStoreClient\UserCredentials;
use Psr\Log\LoggerInterface as Logger;
use SplQueue;
use Throwable;
use function Amp\call;

/** @internal  */
abstract class EventStoreCatchUpSubscription
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

    /** @var EventStoreAsyncConnection */
    private $connection;
    /** @var bool */
    private $resolveLinkTos;
    /** @var UserCredentials|null */
    private $userCredentials;

    /** @var int */
    protected $readBatchSize;
    /** @var int */
    protected $maxPushQueueSize;

    /** @var EventAppearedOnCatchupSubscription */
    protected $eventAppeared;
    /** @var LiveProcessingStarted|null */
    private $liveProcessingStarted;
    /** @var CatchUpSubscriptionDropped|null */
    private $subscriptionDropped;

    /** @var bool */
    protected $verbose;

    /** @var SplQueue<ResolvedEvent> */
    private $liveQueue;
    /** @var EventStoreSubscription */
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
    /** @var bool */
    private $stopped;

    /** @var ListenerHandler */
    private $connectListener;

    public function __construct(
        EventStoreAsyncConnection $connection,
        Logger $logger,
        string $streamId,
        ?UserCredentials $userCredentials,
        EventAppearedOnCatchupSubscription $eventAppeared,
        ?LiveProcessingStarted $liveProcessingStarted,
        ?CatchUpSubscriptionDropped $subscriptionDropped,
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
        $this->connectListener = function (): void {
        };
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
        EventStoreAsyncConnection $connection,
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

    public function stopWithTimeout(int $timeout): void
    {
        $this->stop();

        if ($this->verbose) {
            $this->log->debug(\sprintf(
                'Waiting on subscription %s to stop',
                $this->subscriptionName
            ));
        }

        Loop::delay($timeout, function (): void {
            if (! $this->stopped) {
                throw new TimeoutException('Could not stop in time');
            }
        });
    }

    public function stop(): void
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
            $promise = $this->runSubscriptionAsync();
            Promise\rethrow($promise);

            yield $promise;
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

        $this->stopped = false;
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

            return new Success(true);
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

                $eventAppeared = new class(Closure::fromCallable([$this, 'enqueuePushedEvent'])) implements EventAppearedOnSubscription {
                    private $callback;

                    public function __construct(callable $callback)
                    {
                        $this->callback = $callback;
                    }

                    public function __invoke(
                        EventStoreSubscription $subscription,
                        InternalResolvedEvent $resolvedEvent
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
                $promise = $this->processLiveQueueAsync();
                Promise\rethrow($promise);

                yield $promise;
            });
        }
    }

    private function processLiveQueueAsync(): Promise
    {
        return call(function (): Generator {
            $this->isProcessing = true;
            do {
                /** @var ResolvedEvent $e */
                while (! $this->liveQueue->isEmpty()) {
                    $e = $this->liveQueue->dequeue();

                    if ($e === self::$dropSubscriptionEvent) {
                        $this->dropData = $this->dropData ?? new DropData(SubscriptionDropReason::unknown(), new \Exception('Drop reason not specified'));
                        $this->dropSubscription($this->dropData->reason(), $this->dropData->error());

                        if ($this->isProcessing) {
                            $this->isProcessing = false;
                        }

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

                if ($this->isProcessing) {
                    $this->isProcessing = false;
                }
            } while ($this->liveQueue->count() > 0 && ! $this->isProcessing);

            $this->isProcessing = true;
        });
    }

    public function dropSubscription(SubscriptionDropReason $reason, ?Throwable $error): void
    {
        if (! $this->isDropped) {
            $this->isDropped = true;

            if ($this->verbose) {
                $this->log->debug(\sprintf(
                    'Catch-up Subscription %s to %s: droppen subscription, reason: %s %s',
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

            $this->stopped = true;
        }
    }
}
