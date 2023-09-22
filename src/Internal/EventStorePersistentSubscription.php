<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2022 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2022 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use function Amp\async;
use Amp\DeferredFuture;
use function Amp\delay;
use Amp\TimeoutCancellation;
use Closure;
use Exception;
use Prooph\EventStore\EventId;
use Prooph\EventStore\EventStorePersistentSubscription as EventStorePersistentSubscriptionInterface;
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
use Revolt\EventLoop;
use SplQueue;
use Throwable;

/** @internal */
class EventStorePersistentSubscription implements EventStorePersistentSubscriptionInterface
{
    private ResolvedEvent $dropSubscriptionEvent;

    private ?PersistentEventStoreSubscription $subscription = null;

    /** @var SplQueue */
    private $queue;

    private bool $isProcessing = false;

    private ?DropData $dropData = null;

    private bool $isDropped = false;

    private DeferredFuture $stopped;

    /**
     * @internal
     *
     * @param Closure(EventStorePersistentSubscription, ResolvedEvent, null|int): void $eventAppeared
     * @param null|Closure(EventStorePersistentSubscription, SubscriptionDropReason, null|Throwable): void $subscriptionDropped
     */
    public function __construct(
        private readonly string $subscriptionId,
        private readonly string $streamId,
        private readonly Closure $eventAppeared,
        private readonly ?Closure $subscriptionDropped,
        private readonly ?UserCredentials $userCredentials,
        private readonly Logger $log,
        private readonly bool $verbose,
        private readonly ConnectionSettings $settings,
        private readonly EventStoreConnectionLogicHandler $handler,
        private readonly int $bufferSize = 10,
        private readonly bool $autoAck = true
    ) {
        $this->dropSubscriptionEvent = new ResolvedEvent(null, null, null);
        $this->queue = new SplQueue();
        $this->stopped = new DeferredFuture();
        $this->stopped->complete(true);
    }

    /**
     * @internal
     *
     * @param Closure(EventStorePersistentSubscription, ResolvedEvent, null|int): void $eventAppeared
     * @param null|Closure(EventStorePersistentSubscription, SubscriptionDropReason, null|Throwable): void $subscriptionDropped
     */
    public function startSubscription(
        string $subscriptionId,
        string $streamId,
        int $bufferSize,
        ?UserCredentials $userCredentials,
        Closure $onEventAppeared,
        ?Closure $onSubscriptionDropped,
        ConnectionSettings $settings
    ): PersistentEventStoreSubscription {
        $deferred = new DeferredFuture();

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

        return $deferred->getFuture()->await();
    }

    /**
     * @internal
     */
    public function start(): void
    {
        $this->stopped = new DeferredFuture();

        $this->subscription = $this->startSubscription(
            $this->subscriptionId,
            $this->streamId,
            $this->bufferSize,
            $this->userCredentials,
            function (
                PersistentEventStoreSubscription $subscription,
                PersistentSubscriptionResolvedEvent $resolvedEvent
            ): void {
                $this->onEventAppeared($resolvedEvent);
            },
            function (
                PersistentEventStoreSubscription $subscription,
                SubscriptionDropReason $reason,
                ?Throwable $exception
            ): void {
                $this->onSubscriptionDropped($reason, $exception);
            },
            $this->settings
        );
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

    public function stop(?float $timeout = null): void
    {
        if ($this->verbose) {
            $this->log->debug(\sprintf(
                'Persistent Subscription to %s: requesting stop...',
                $this->streamId
            ));
        }

        $this->enqueueSubscriptionDropNotification(SubscriptionDropReason::UserInitiated, null);

        if (null === $timeout) {
            return;
        }

        $this->stopped->getFuture()->await(new TimeoutCancellation($timeout));
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
    ): void {
        $this->enqueue($resolvedEvent);
    }

    private function enqueue(PersistentSubscriptionResolvedEvent $resolvedEvent): void
    {
        $this->queue->enqueue($resolvedEvent);

        if (! $this->isProcessing) {
            $this->isProcessing = true;

            EventLoop::defer(function (): void {
                $this->processQueue();
            });
        }
    }

    private function processQueue(): void
    {
        async(function (): void {
            do {
                if (null === $this->subscription) {
                    delay(1);
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
                            ($this->eventAppeared)($this, $e->event(), $e->retryCount());

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

                            $this->dropSubscription(SubscriptionDropReason::EventHandlerException, $ex);

                            return;
                        }
                    }
                }
            } while (! $this->queue->isEmpty() && $this->isProcessing);

            $this->isProcessing = false;
        })->await();
    }

    private function dropSubscription(SubscriptionDropReason $reason, ?Throwable $error): void
    {
        if (! $this->isDropped) {
            $this->isDropped = true;

            if ($this->verbose) {
                $this->log->debug(\sprintf(
                    'Persistent Subscription to %s: dropping subscription, reason: %s %s',
                    $this->streamId,
                    $reason->name,
                    null === $error ? '' : $error->getMessage()
                ));
            }

            $this->subscription?->unsubscribe();

            if ($this->subscriptionDropped) {
                ($this->subscriptionDropped)($this, $reason, $error);
            }

            $this->stopped->complete(true);
        }
    }
}
