<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2020 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2020 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use function Amp\call;
use Amp\Deferred;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;
use Closure;
use Generator;
use Prooph\EventStore\Async\ClientConnectionEventArgs;
use Prooph\EventStore\Async\EventStoreCatchUpSubscription as AsyncEventStoreCatchUpSubscription;
use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\CatchUpSubscriptionSettings;
use Prooph\EventStore\EventStoreSubscription;
use Prooph\EventStore\Internal\DropData;
use Prooph\EventStore\ListenerHandler;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SubscriptionDropReason;
use Prooph\EventStore\UserCredentials;
use Psr\Log\LoggerInterface as Logger;
use SplQueue;
use Throwable;

abstract class EventStoreCatchUpSubscription implements AsyncEventStoreCatchUpSubscription
{
    private ResolvedEvent $dropSubscriptionEvent;
    private bool $isSubscribedToAll;
    private string $streamId;
    private string $subscriptionName;
    protected Logger $log;
    private EventStoreConnection $connection;
    private bool $resolveLinkTos;
    private ?UserCredentials $userCredentials;
    protected int $readBatchSize;
    protected int $maxPushQueueSize;
    /** @var Closure(EventStoreCatchUpSubscription, ResolvedEvent): Promise */
    protected Closure $eventAppeared;
    /** @var null|Closure(EventStoreCatchUpSubscription): void  */
    private ?Closure $liveProcessingStarted;
    /** @var null|Closure(EventStoreCatchUpSubscription, SubscriptionDropReason, null|Throwable): void */
    private ?Closure $subscriptionDropped;
    protected bool $verbose;
    /** @var SplQueue<ResolvedEvent> */
    private SplQueue $liveQueue;
    private ?EventStoreSubscription $subscription = null;
    private ?DropData $dropData = null;
    private bool $allowProcessing = false;
    private bool $isProcessing = false;
    protected bool $shouldStop = false;
    private bool $isDropped = false;
    private Deferred $stopped;
    private ListenerHandler $connectListener;

    /**
     * @param Closure(EventStoreCatchUpSubscription, ResolvedEvent): Promise $eventAppeared
     * @param null|Closure(EventStoreCatchUpSubscription): void $liveProcessingStarted
     * @param null|Closure(EventStoreCatchUpSubscription, SubscriptionDropReason, null|Throwable): void $subscriptionDropped
     *
     * @internal
     */
    public function __construct(
        EventStoreConnection $connection,
        Logger $logger,
        string $streamId,
        ?UserCredentials $userCredentials,
        Closure $eventAppeared,
        ?Closure $liveProcessingStarted,
        ?Closure $subscriptionDropped,
        CatchUpSubscriptionSettings $settings
    ) {
        $this->dropSubscriptionEvent = new ResolvedEvent(null, null, null);
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
        $this->subscriptionName = $settings->subscriptionName();
        $this->connectListener = new ListenerHandler(function (): void {
        });
        $this->stopped = new Deferred();
        $this->stopped->resolve(true);
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
        EventStoreConnection $connection,
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

        return Promise\timeoutWithDefault($this->stopped->promise(), $timeout);
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

        $this->stopped = new Deferred();
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

                $eventAppeared = function (
                    EventStoreSubscription $subscription,
                    ResolvedEvent $resolvedEvent
                ): Promise {
                    return $this->enqueuePushedEvent($subscription, $resolvedEvent);
                };

                $subscriptionDropped = function (
                    EventStoreSubscription $subscription,
                    SubscriptionDropReason $reason,
                    ?Throwable $exception = null
                ): void {
                    $this->serverSubscriptionDropped($reason, $exception);
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

                /** @psalm-suppress PossiblyNullReference */
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
            /** @psalm-suppress PossiblyNullReference */
            $this->log->debug(\sprintf(
                'Catch-up Subscription %s to %s: event appeared (%s, %s, %s, @ %s)',
                $this->subscriptionName,
                $this->isSubscribedToAll ? '<all>' : $this->streamId,
                $e->originalStreamName(),
                $e->originalEventNumber(),
                $e->originalEvent()->eventType(),
                (string) $e->originalPosition()
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

            $this->liveQueue->enqueue($this->dropSubscriptionEvent);

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

                    if ($e === $this->dropSubscriptionEvent) {
                        $this->dropData ??= new DropData(SubscriptionDropReason::unknown(), new \Exception('Drop reason not specified'));
                        $this->dropSubscription($this->dropData->reason(), $this->dropData->error());

                        $this->isProcessing = false;

                        return;
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

                        return;
                    }
                }
            } while ($this->liveQueue->count() > 0);

            $this->isProcessing = false;
        });
    }

    protected function dropSubscription(SubscriptionDropReason $reason, ?Throwable $error): void
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

            $this->stopped->resolve(true);
        }
    }
}
