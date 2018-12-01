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

use Amp\Deferred;
use Amp\Delayed;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;
use Generator;
use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStoreClient\EventAppearedOnPersistentSubscription;
use Prooph\EventStoreClient\EventAppearedOnSubscription;
use Prooph\EventStoreClient\EventId;
use Prooph\EventStoreClient\EventStoreSubscription;
use Prooph\EventStoreClient\Exception\RuntimeException;
use Prooph\EventStoreClient\Internal\Message\StartPersistentSubscriptionMessage;
use Prooph\EventStoreClient\Internal\ResolvedEvent as InternalResolvedEvent;
use Prooph\EventStoreClient\PersistentSubscriptionDropped;
use Prooph\EventStoreClient\PersistentSubscriptionNakEventAction;
use Prooph\EventStoreClient\PersistentSubscriptionResolvedEvent;
use Prooph\EventStoreClient\ResolvedEvent;
use Prooph\EventStoreClient\SubscriptionDropped;
use Prooph\EventStoreClient\SubscriptionDropReason;
use Prooph\EventStoreClient\UserCredentials;
use Psr\Log\LoggerInterface as Logger;
use SplQueue;
use Throwable;
use function Amp\call;

class EventStorePersistentSubscription
{
    /** @var EventStoreConnectionLogicHandler */
    private $handler;

    /** @var ResolvedEvent */
    private static $dropSubscriptionEvent;

    public const DEFAULT_BUFFER_SIZE = 10;

    /** @var string */
    private $subscriptionId;
    /** @var string */
    private $streamId;
    /** @var EventAppearedOnPersistentSubscription */
    private $eventAppeared;
    /** @var PersistentSubscriptionDropped|null */
    private $subscriptionDropped;
    /** @var UserCredentials|null */
    private $userCredentials;
    /** @var Logger */
    private $log;
    /** @var bool */
    private $verbose;
    /** @var ConnectionSettings */
    private $settings;
    /** @var bool */
    private $autoAck;

    /** @var PersistentEventStoreSubscription */
    private $subscription;
    /** @var SplQueue */
    private $queue;
    /** @var bool */
    private $isProcessing = false;
    /** @var DropData */
    private $dropData;

    /** @var int */
    private $isDropped;
    /** @var int */
    private $bufferSize;
    /** @var ManualResetEventSlim */
    private $stopped;

    /** @internal  */
    public function __construct(
        string $subscriptionId,
        string $streamId,
        EventAppearedOnPersistentSubscription $eventAppeared,
        ?PersistentSubscriptionDropped $subscriptionDropped,
        ?UserCredentials $userCredentials,
        Logger $logger,
        bool $verboseLogging,
        ConnectionSettings $settings,
        EventStoreConnectionLogicHandler $handler,
        int $bufferSize = 10,
        bool $autoAck = true
    ) {
        if (null === self::$dropSubscriptionEvent) {
            self::$dropSubscriptionEvent = new ResolvedEvent(null, null, null);
        }

        $this->subscriptionId = $subscriptionId;
        $this->streamId = $streamId;
        $this->eventAppeared = $eventAppeared;
        $this->subscriptionDropped = $subscriptionDropped;
        $this->userCredentials = $userCredentials;
        $this->log = $logger;
        $this->verbose = $verboseLogging;
        $this->settings = $settings;
        $this->bufferSize = $bufferSize;
        $this->autoAck = $autoAck;
        $this->queue = new SplQueue();
        $this->stopped = new ManualResetEventSlim(true);
        $this->handler = $handler;
    }

    public function startSubscription(
        string $subscriptionId,
        string $streamId,
        int $bufferSize,
        ?UserCredentials $userCredentials,
        EventAppearedOnSubscription $onEventAppeared,
        ?SubscriptionDropped $onSubscriptionDropped,
        ConnectionSettings $settings
    ): Promise {
        $deferred = new Deferred();

        $this->handler->enqueueMessage(new StartPersistentSubscriptionMessage(
            $deferred,
            $subscriptionId,
            $streamId,
            $bufferSize,
            $userCredentials,
            $onEventAppeared,
            $onSubscriptionDropped,
            $settings->maxRetries(),
            $settings->operationTimeout()
        ));

        return $deferred->promise();
    }

    /**
     * @internal
     *
     * @return Promise<self>
     */
    public function start(): Promise
    {
        $this->stopped->reset();

        $eventAppearedCallback = function (
            EventStoreSubscription $subscription,
            PersistentSubscriptionResolvedEvent $resolvedEvent
        ): Promise {
            return $this->onEventAppeared($resolvedEvent);
        };

        $subscriptionDroppedCallback = function (
            EventStoreSubscription $subscription,
            SubscriptionDropReason $reason,
            ?Throwable $exception
        ): void {
            $this->onSubscriptionDropped($reason, $exception);
        };

        $eventAppeared = new class($eventAppearedCallback) implements EventAppearedOnSubscription {
            private $callback;

            public function __construct(callable  $callback)
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

        $subscriptionDropped = new class($subscriptionDroppedCallback) implements SubscriptionDropped {
            private $callback;

            public function __construct(callable  $callback)
            {
                $this->callback = $callback;
            }

            public function __invoke(
                EventStoreSubscription $subscription,
                SubscriptionDropReason $reason,
                ?Throwable $exception = null
            ): void {
                ($this->callback)($subscription, $reason, $exception);
            }
        };

        $promise = $this->startSubscription(
            $this->subscriptionId,
            $this->streamId,
            $this->bufferSize,
            $this->userCredentials,
            $eventAppeared,
            $subscriptionDropped,
            $this->settings
        );

        $deferred = new Deferred();

        $promise->onResolve(function (?Throwable $exception, $result) use ($deferred) {
            if ($exception) {
                $deferred->fail($exception);

                return;
            }

            $this->subscription = $result;
            $deferred->resolve($this);
        });

        return $deferred->promise();
    }

    /**
     * Acknowledge that a message have completed processing (this will tell the server it has been processed)
     * Note: There is no need to ack a message if you have Auto Ack enabled
     *
     * @param InternalResolvedEvent $event
     *
     * @return void
     */
    public function acknowledge(InternalResolvedEvent $event): void
    {
        $this->subscription->notifyEventsProcessed([$event->originalEvent()->eventId()]);
    }

    /**
     * Acknowledge that a message have completed processing (this will tell the server it has been processed)
     * Note: There is no need to ack a message if you have Auto Ack enabled
     *
     * @param InternalResolvedEvent[] $events
     *
     * @return void
     */
    public function acknowledgeMultiple(array $events): void
    {
        $ids = \array_map(
            function (InternalResolvedEvent $event): EventId {
                return $event->originalEvent()->eventId();
            },
            $events
        );

        $this->subscription->notifyEventsProcessed($ids);
    }

    /**
     * Acknowledge that a message have completed processing (this will tell the server it has been processed)
     * Note: There is no need to ack a message if you have Auto Ack enabled
     *
     * @param EventId $eventId
     *
     * @return void
     */
    public function acknowledgeEventId(EventId $eventId): void
    {
        $this->subscription->notifyEventsProcessed([$eventId]);
    }

    /**
     * Acknowledge that a message have completed processing (this will tell the server it has been processed)
     * Note: There is no need to ack a message if you have Auto Ack enabled
     *
     * @param EventId[] $eventIds
     *
     * @return void
     */
    public function acknowledgeMultipleEventIds(array $eventIds): void
    {
        $this->subscription->notifyEventsProcessed($eventIds);
    }

    /**
     * Mark a message failed processing. The server will be take action based upon the action paramter
     */
    public function fail(
        InternalResolvedEvent $event,
        PersistentSubscriptionNakEventAction $action,
        string $reason
    ): void {
        $this->subscription->notifyEventsFailed([$event->originalEvent()->eventId()], $action, $reason);
    }

    /**
     * Mark n messages that have failed processing. The server will take action based upon the action parameter
     *
     * @param InternalResolvedEvent[] $events
     * @param PersistentSubscriptionNakEventAction $action
     * @param string $reason
     */
    public function failMultiple(
        array $events,
        PersistentSubscriptionNakEventAction $action,
        string $reason
    ): void {
        $ids = \array_map(
            function (InternalResolvedEvent $event): EventId {
                return $event->originalEvent()->eventId();
            },
            $events
        );

        $this->subscription->notifyEventsFailed($ids, $action, $reason);
    }

    public function stop(?int $timeout = null): Promise
    {
        if ($this->verbose) {
            $this->log->debug(\sprintf(
                'Persistent Subscription to %s: requesting stop...',
                $this->streamId
            ));
        }

        $this->enqueueSubscriptionDropNotification(SubscriptionDropReason::userInitiated(), null);

        if (null === $timeout) {
            return new Success();
        }

        return $this->stopped->wait($timeout);
    }

    private function enqueueSubscriptionDropNotification(
        SubscriptionDropReason $reason,
        ?Throwable $error
    ): void {
        // if drop data was already set -- no need to enqueue drop again, somebody did that already
        if (null === $this->dropData) {
            $this->dropData = new DropData($reason, $error);

            $this->enqueue(
                new PersistentSubscriptionResolvedEvent(self::$dropSubscriptionEvent, null)
            );
        }
    }

    private function onSubscriptionDropped(
        SubscriptionDropReason $reason,
        ?Throwable $exception): void
    {
        $this->enqueueSubscriptionDropNotification($reason, $exception);
    }

    private function onEventAppeared(
        PersistentSubscriptionResolvedEvent $resolvedEvent
    ): Promise {
        $this->enqueue($resolvedEvent);

        return new Success();
    }

    private function enqueue(PersistentSubscriptionResolvedEvent $resolvedEvent): void
    {
        $this->queue[] = $resolvedEvent;

        if (! $this->isProcessing) {
            $this->isProcessing = true;

            Loop::defer(function (): Generator {
                yield $this->processQueue();
            });
        }
    }

    /** @return Promise<void> */
    private function processQueue(): Promise
    {
        return call(function (): Generator {
            do {
                if (null === $this->subscription) {
                    yield new Delayed(1000);
                } else {
                    while (! $this->queue->isEmpty()) {
                        $e = $this->queue->dequeue();
                        \assert($e instanceof PersistentSubscriptionResolvedEvent);

                        if ($e->event() === self::$dropSubscriptionEvent) {
                            // drop subscription artificial ResolvedEvent

                            if (null === $this->dropData) {
                                throw new RuntimeException('Drop reason not specified');
                            }

                            $this->dropSubscription($this->dropData->reason(), $this->dropData->error());

                            return null;
                        }

                        if (null !== $this->dropData) {
                            $this->dropSubscription($this->dropData->reason(), $this->dropData->error());

                            return null;
                        }

                        try {
                            yield ($this->eventAppeared)($this, $e->event(), $e->retryCount());

                            if ($this->autoAck) {
                                $this->subscription->notifyEventsProcessed([$e->originalEvent()->eventId()]);
                            }

                            if ($this->verbose) {
                                $this->log->debug(\sprintf(
                                    'Persistent Subscription to %s: processed event (%s, %d, %s @ %d)',
                                    $this->streamId,
                                    $e->originalEvent()->eventStreamId(),
                                    $e->originalEvent()->eventNumber(),
                                    $e->originalEvent()->eventType(),
                                    $e->event()->originalEventNumber()
                                ));
                            }
                        } catch (Throwable $ex) {
                            //TODO GFY should we autonak here?

                            $this->dropSubscription(SubscriptionDropReason::eventHandlerException(), $ex);

                            return null;
                        }
                    }
                }
            } while (! $this->queue->isEmpty() && $this->isProcessing);

            $this->isProcessing = false;
        });
    }

    private function dropSubscription(SubscriptionDropReason $reason, ?Throwable $error): void
    {
        if (! $this->isDropped) {
            if ($this->verbose) {
                $this->log->debug(\sprintf(
                    'Persistent Subscription to %s: dropping subscription, reason: %s %s',
                    $this->streamId,
                    $reason->name(),
                    null === $error ? '' : $error->getMessage()
                ));
            }

            if (null !== $this->subscription) {
                $this->subscription->unsubscribe();
            }

            if ($this->subscriptionDropped) {
                ($this->subscriptionDropped)($this, $reason, $error);
            }

            $this->stopped->set();
        }
    }
}
