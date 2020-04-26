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
use Amp\Delayed;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;
use Closure;
use Exception;
use Generator;
use Prooph\EventStore\Async\EventStorePersistentSubscription as AsyncEventStorePersistentSubscription;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStore\Internal\DropData;
use Prooph\EventStore\Internal\PersistentEventStoreSubscription;
use Prooph\EventStore\Internal\ResolvedEvent as InternalResolvedEvent;
use Prooph\EventStore\PersistentSubscriptionNakEventAction;
use Prooph\EventStore\PersistentSubscriptionResolvedEvent;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SubscriptionDropReason;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStoreClient\Internal\Message\StartPersistentSubscriptionMessage;
use Psr\Log\LoggerInterface as Logger;
use SplQueue;
use Throwable;

class EventStorePersistentSubscription implements AsyncEventStorePersistentSubscription
{
    private EventStoreConnectionLogicHandler $handler;
    private ResolvedEvent $dropSubscriptionEvent;
    private string $subscriptionId;
    private string $streamId;
    /** @var Closure(EventStorePersistentSubscription, ResolvedEvent, null|int): Promise $eventAppeared */
    private Closure $eventAppeared;
    /** @var null|Closure(EventStorePersistentSubscription, SubscriptionDropReason, null|Throwable): void $subscriptionDropped */
    private ?Closure $subscriptionDropped;
    private ?UserCredentials $userCredentials;
    private Logger $log;
    private bool $verbose;
    private ConnectionSettings $settings;
    private bool $autoAck;
    private ?PersistentEventStoreSubscription $subscription = null;
    /** @var SplQueue */
    private $queue;
    private bool $isProcessing = false;
    private ?DropData $dropData = null;
    private bool $isDropped = false;
    private int $bufferSize;
    private Deferred $stopped;

    /**
     * @internal
     *
     * @param Closure(EventStorePersistentSubscription, ResolvedEvent, null|int): Promise $eventAppeared
     * @param null|Closure(EventStorePersistentSubscription, SubscriptionDropReason, null|Throwable): void $subscriptionDropped
     */
    public function __construct(
        string $subscriptionId,
        string $streamId,
        Closure $eventAppeared,
        ?Closure $subscriptionDropped,
        ?UserCredentials $userCredentials,
        Logger $logger,
        bool $verboseLogging,
        ConnectionSettings $settings,
        EventStoreConnectionLogicHandler $handler,
        int $bufferSize = 10,
        bool $autoAck = true
    ) {
        $this->dropSubscriptionEvent = new ResolvedEvent(null, null, null);
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
        $this->stopped = new Deferred();
        $this->stopped->resolve(true);
        $this->handler = $handler;
    }

    /**
     * @internal
     *
     * @param Closure(EventStorePersistentSubscription, ResolvedEvent, null|int): Promise $eventAppeared
     * @param null|Closure(EventStorePersistentSubscription, SubscriptionDropReason, null|Throwable): void $subscriptionDropped
     *
     * @return Promise<PersistentEventStoreSubscription>
     */
    public function startSubscription(
        string $subscriptionId,
        string $streamId,
        int $bufferSize,
        ?UserCredentials $userCredentials,
        Closure $onEventAppeared,
        ?Closure $onSubscriptionDropped,
        ConnectionSettings $settings,
        Promise $stopped
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
            $settings->operationTimeout(),
            $stopped
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
        $this->stopped = new Deferred();

        $eventAppeared = fn (PersistentEventStoreSubscription $subscription, PersistentSubscriptionResolvedEvent $resolvedEvent): Promise => $this->onEventAppeared($resolvedEvent);

        $subscriptionDropped = function (
            PersistentEventStoreSubscription $subscription,
            SubscriptionDropReason $reason,
            ?Throwable $exception
        ): void {
            $this->onSubscriptionDropped($reason, $exception);
        };

        $promise = $this->startSubscription(
            $this->subscriptionId,
            $this->streamId,
            $this->bufferSize,
            $this->userCredentials,
            $eventAppeared,
            $subscriptionDropped,
            $this->settings,
            $this->stopped->promise()
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
     */
    public function acknowledge(InternalResolvedEvent $event): void
    {
        /** @psalm-suppress PossiblyNullReference */
        $this->subscription->notifyEventsProcessed([$event->originalEvent()->eventId()]);
    }

    /**
     * Acknowledge that a message have completed processing (this will tell the server it has been processed)
     * Note: There is no need to ack a message if you have Auto Ack enabled
     *
     * @param list<InternalResolvedEvent> $events
     */
    public function acknowledgeMultiple(array $events): void
    {
        $ids = \array_map(
            /** @psalm-suppress PossiblyNullReference */
            fn (InternalResolvedEvent $event): EventId => $event->originalEvent()->eventId(),
            $events
        );

        /** @psalm-suppress PossiblyNullReference */
        $this->subscription->notifyEventsProcessed($ids);
    }

    /**
     * Acknowledge that a message have completed processing (this will tell the server it has been processed)
     * Note: There is no need to ack a message if you have Auto Ack enabled
     */
    public function acknowledgeEventId(EventId $eventId): void
    {
        /** @psalm-suppress PossiblyNullReference */
        $this->subscription->notifyEventsProcessed([$eventId]);
    }

    /**
     * Acknowledge that a message have completed processing (this will tell the server it has been processed)
     * Note: There is no need to ack a message if you have Auto Ack enabled
     *
     * @param list<EventId> $eventIds
     */
    public function acknowledgeMultipleEventIds(array $eventIds): void
    {
        /** @psalm-suppress PossiblyNullReference */
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
        /** @psalm-suppress PossiblyNullReference */
        $this->subscription->notifyEventsFailed([$event->originalEvent()->eventId()], $action, $reason);
    }

    /**
     * Mark n messages that have failed processing. The server will take action based upon the action parameter
     *
     * @param list<InternalResolvedEvent> $events
     */
    public function failMultiple(
        array $events,
        PersistentSubscriptionNakEventAction $action,
        string $reason
    ): void {
        $ids = \array_map(
            /** @psalm-suppress PossiblyNullReference */
            fn (InternalResolvedEvent $event): EventId => $event->originalEvent()->eventId(),
            $events
        );

        /** @psalm-suppress PossiblyNullReference */
        $this->subscription->notifyEventsFailed($ids, $action, $reason);
    }

    public function failEventId(EventId $eventId, PersistentSubscriptionNakEventAction $action, string $reason): void
    {
        /** @psalm-suppress PossiblyNullReference */
        $this->subscription->notifyEventsFailed([$eventId], $action, $reason);
    }

    /**
     * @param list<EventId> $eventIds
     */
    public function failMultipleEventIds(array $eventIds, PersistentSubscriptionNakEventAction $action, string $reason): void
    {
        /** @psalm-suppress PossiblyNullReference */
        $this->subscription->notifyEventsFailed($eventIds, $action, $reason);
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

        return Promise\timeoutWithDefault($this->stopped->promise(), $timeout);
    }

    private function enqueueSubscriptionDropNotification(
        SubscriptionDropReason $reason,
        ?Throwable $error
    ): void {
        // if drop data was already set -- no need to enqueue drop again, somebody did that already
        if (null === $this->dropData) {
            $this->dropData = new DropData($reason, $error);

            $this->enqueue(
                new PersistentSubscriptionResolvedEvent($this->dropSubscriptionEvent, null)
            );
        }
    }

    private function onSubscriptionDropped(
        SubscriptionDropReason $reason,
        ?Throwable $exception
    ): void {
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
        $this->queue->enqueue($resolvedEvent);

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

                        if ($e->event() === $this->dropSubscriptionEvent) {
                            // drop subscription artificial ResolvedEvent

                            if (null === $this->dropData) {
                                throw new RuntimeException('Drop reason not specified');
                            }

                            $this->dropSubscription($this->dropData->reason(), $this->dropData->error());

                            return;
                        }

                        if (null !== $this->dropData) {
                            $this->dropSubscription($this->dropData->reason(), $this->dropData->error());

                            return;
                        }

                        try {
                            yield ($this->eventAppeared)($this, $e->event(), $e->retryCount());

                            if ($this->autoAck) {
                                /** @psalm-suppress PossiblyNullReference */
                                $this->subscription->notifyEventsProcessed([$e->originalEvent()->eventId()]);
                            }

                            if ($this->verbose) {
                                /** @psalm-suppress PossiblyNullReference */
                                $this->log->debug(\sprintf(
                                    'Persistent Subscription to %s: processed event (%s, %d, %s @ %d)',
                                    $this->streamId,
                                    $e->originalEvent()->eventStreamId(),
                                    $e->originalEvent()->eventNumber(),
                                    $e->originalEvent()->eventType(),
                                    $e->event()->originalEventNumber()
                                ));
                            }
                        } catch (Exception $ex) {
                            //TODO GFY should we autonak here?

                            $this->dropSubscription(SubscriptionDropReason::eventHandlerException(), $ex);

                            return;
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
            $this->isDropped = true;

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

            $this->stopped->resolve(true);
        }
    }
}
