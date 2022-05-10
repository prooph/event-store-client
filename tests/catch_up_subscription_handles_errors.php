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

namespace ProophTest\EventStoreClient;

use Amp\CancelledException;
use Amp\DeferredFuture;
use function Amp\delay;
use Amp\PHPUnit\AsyncTestCase;
use Amp\TimeoutCancellation;
use Exception;
use Prooph\EventStore\CatchUpSubscriptionSettings;
use Prooph\EventStore\ClientConnectionEventArgs;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\EventId;
use Prooph\EventStore\EventStoreCatchUpSubscription;
use Prooph\EventStore\EventStoreSubscription;
use Prooph\EventStore\ReadDirection;
use Prooph\EventStore\RecordedEvent;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\SubscriptionDropReason;
use Prooph\EventStore\Util\DateTime;
use Prooph\EventStoreClient\ClientOperations\VolatileSubscriptionOperation;
use Prooph\EventStoreClient\Internal\EventStoreStreamCatchUpSubscription;
use Prooph\EventStoreClient\Internal\VolatileEventStoreSubscription;
use Psr\Log\NullLogger;
use Revolt\EventLoop;
use Throwable;

class catch_up_subscription_handles_errors extends AsyncTestCase
{
    private const Timeout = 2;

    private const StreamId = 'stream1';

    private FakeEventStoreConnection $connection;

    private array $raisedEvents;

    private bool $liveProcessingStarted;

    private bool $isDropped;

    private DeferredFuture $dropEvent;

    private DeferredFuture $raisedEventEvent;

    private ?Throwable $dropException;

    private SubscriptionDropReason $dropReason;

    private EventStoreStreamCatchUpSubscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = new FakeEventStoreConnection();
        $this->raisedEvents = [];
        $this->dropEvent = new DeferredFuture();
        $this->raisedEventEvent = new DeferredFuture();
        $this->liveProcessingStarted = false;
        $this->isDropped = false;
        $this->dropReason = SubscriptionDropReason::Unknown;
        $this->dropException = null;

        $props1 = [
            'raisedEvents' => &$this->raisedEvents,
            'raisedEventEvent' => $this->raisedEventEvent,
        ];

        $props2 = [
            'liveProcessingStarted' => &$this->liveProcessingStarted,
        ];

        $props3 = [
            'isDropped' => &$this->isDropped,
            'dropReason' => &$this->dropReason,
            'dropException' => &$this->dropException,
            'dropEvent' => $this->dropEvent,
        ];

        $settings = new CatchUpSubscriptionSettings(1, 1, false, false);
        $this->subscription = new EventStoreStreamCatchUpSubscription(
            $this->connection,
            new NullLogger(),
            self::StreamId,
            null,
            null,
            function (
                EventStoreCatchUpSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ) use (&$props1): void {
                $props1['raisedEvents'][] = $resolvedEvent;

                try {
                    $props1['raisedEventEvent']->complete(true);
                } catch (Throwable $e) {
                    // on connection close this event may appear twice, just ignore second occurrence
                }
            },
            function (EventStoreCatchUpSubscription $subscription) use (&$props2): void {
                $props2['liveProcessingStarted'] = true;
            },
            function (
                EventStoreCatchUpSubscription $subscription,
                SubscriptionDropReason $reason,
                ?Throwable $exception = null
            ) use (&$props3): void {
                $props3['isDropped'] = true;
                $props3['dropReason'] = $reason;
                $props3['dropException'] = $exception;
                $props3['dropEvent']->complete(true);
            },
            $settings
        );
    }

    /** @test */
    public function read_events_til_stops_subscription_when_throws_immediately(): void
    {
        $expectedException = new Exception('Test');

        $this->connection->handleReadStreamEventsForward(
            function ($stream, $start, $max) use ($expectedException): StreamEventsSlice {
                $this->assertSame(self::StreamId, $stream);
                $this->assertSame(0, $start);
                $this->assertSame(1, $max);

                throw $expectedException;
            }
        );

        $this->assertStartFailsAndDropsSubscriptionWithException($expectedException);
    }

    private function assertStartFailsAndDropsSubscriptionWithException(Exception $expectedException): void
    {
        try {
            $this->subscription->start();
        } catch (Throwable $e) {
            $this->assertTrue($this->isDropped);
            $this->assertSame(SubscriptionDropReason::CatchUpError, $this->dropReason);
            $this->assertSame($expectedException, $this->dropException);
            $this->assertFalse($this->liveProcessingStarted);

            return;
        }

        $this->fail('No exception thrown');
    }

    /** @test */
    public function read_events_til_stops_subscription_when_throws_asynchronously(): void
    {
        $expectedException = new Exception('Test');

        $this->connection->handleReadStreamEventsForward(
            function ($stream, $start, $max) use ($expectedException): StreamEventsSlice {
                $this->assertSame(self::StreamId, $stream);
                $this->assertSame(0, $start);
                $this->assertSame(1, $max);

                throw $expectedException;
            }
        );

        $this->assertStartFailsAndDropsSubscriptionWithException($expectedException);
    }

    /** @test */
    public function read_events_til_stops_subscription_when_second_read_throws_immediately(): void
    {
        $expectedException = new Exception('Test');

        $callCount = 0;

        $this->connection->handleReadStreamEventsForward(
            function ($stream, $start, $max) use (&$callCount, $expectedException): StreamEventsSlice {
                $this->assertSame(self::StreamId, $stream);
                $this->assertSame(1, $max);

                if ($callCount++ === 0) {
                    $this->assertSame(0, $start);

                    return $this->createStreamEventsSlice();
                }

                $this->assertSame(1, $start);

                throw $expectedException;
            }
        );

        $this->assertStartFailsAndDropsSubscriptionWithException($expectedException);

        $this->assertCount(1, $this->raisedEvents);
    }

    /** @test */
    public function start_stops_subscription_if_subscribe_fails_immediately(): void
    {
        $expectedException = new Exception('Test');

        $this->connection->handleReadStreamEventsForward(
            fn (): StreamEventsSlice => $this->createStreamEventsSlice(0, 1, true)
        );

        $this->connection->handleSubscribeToStream(
            function ($stream, $raise, $drop) use ($expectedException): VolatileEventStoreSubscription {
                $this->assertSame(self::StreamId, $stream);

                throw $expectedException;
            }
        );

        $this->assertStartFailsAndDropsSubscriptionWithException($expectedException);
        $this->assertCount(1, $this->raisedEvents);
    }

    /** @test */
    public function start_stops_subscription_if_subscribe_fails_(): void
    {
        $expectedException = new Exception('Test');

        $this->connection->handleReadStreamEventsForward(
            fn (): StreamEventsSlice => $this->createStreamEventsSlice(0, 1, true)
        );

        $this->connection->handleSubscribeToStream(
            fn (): VolatileEventStoreSubscription => throw $expectedException
        );

        $this->assertStartFailsAndDropsSubscriptionWithException($expectedException);
        $this->assertCount(1, $this->raisedEvents);
    }

    /** @test */
    public function start_stops_subscription_if_historical_missed_events_load_fails_immediate(): void
    {
        $expectedException = new Exception('Test');

        $callCount = 0;

        $this->connection->handleReadStreamEventsForward(
            function ($stream, $start, $max) use (&$callCount, $expectedException): StreamEventsSlice {
                if ($callCount++ === 0) {
                    $this->assertSame(0, $start);

                    return $this->createStreamEventsSlice(0, 1, true);
                }

                $this->assertSame(1, $start);

                throw $expectedException;
            }
        );

        $this->connection->handleSubscribeToStream(
            fn ($stream, $raise, $drop): VolatileEventStoreSubscription => $this->createVolatileSubscription($raise, $drop, 1)
        );

        $this->assertStartFailsAndDropsSubscriptionWithException($expectedException);
        $this->assertCount(1, $this->raisedEvents);
    }

    /** @test */
    public function start_stops_subscription_if_historical_missed_events_load_fails_(): void
    {
        $expectedException = new Exception('Test');

        $callCount = 0;

        $this->connection->handleReadStreamEventsForward(
            function ($stream, $start, $max) use (&$callCount, $expectedException): StreamEventsSlice {
                if ($callCount++ === 0) {
                    $this->assertSame(0, $start);

                    return $this->createStreamEventsSlice(0, 1, true);
                }

                $this->assertSame(1, $start);

                throw $expectedException;
            }
        );

        $this->connection->handleSubscribeToStream(
            fn ($stream, $raise, $drop): VolatileEventStoreSubscription => $this->createVolatileSubscription($raise, $drop, 1)
        );

        $this->assertStartFailsAndDropsSubscriptionWithException($expectedException);
        $this->assertCount(1, $this->raisedEvents);
    }

    /**
     * @test
     * @group by
     */
    public function start_completes_once_subscription_is_live(): void
    {
        $finalEvent = new DeferredFuture();

        $callCount = 0;

        $this->connection->handleReadStreamEventsForward(
            function ($stream, $start, $max) use (&$callCount, $finalEvent): StreamEventsSlice {
                $callCount++;

                if (1 === $callCount) {
                    return $this->createStreamEventsSlice(0, 1, true);
                }

                if (2 === $callCount) {
                    try {
                        $result = $finalEvent->getFuture()->await(new TimeoutCancellation(self::Timeout));
                    } catch (CancelledException $e) {
                        $result = false;
                    }

                    $this->assertTrue($result);

                    return $this->createStreamEventsSlice(1, 1, true);
                }

                $this->fail('Should not happen');
            }
        );

        $this->connection->handleSubscribeToStream(
            fn ($stream, $raise, $drop): VolatileEventStoreSubscription => $this->createVolatileSubscription($raise, $drop, 1)
        );

        EventLoop::defer(function (): void {
            $this->subscription->start();
        });

        $finalEvent->complete(true);
    }

    /** @test */
    public function when_live_processing_and_disconnected_reconnect_keeps_events_ordered(): void
    {
        $callCount = 0;

        $this->connection->handleReadStreamEventsForward(
            function ($stream, $start, $max) use (&$callCount): StreamEventsSlice {
                $callCount++;

                if (1 === $callCount) {
                    return $this->createStreamEventsSlice(0, 0, true);
                }

                if (2 === $callCount) {
                    return $this->createStreamEventsSlice(0, 0, true);
                }

                throw new Exception('Error');
            }
        );

        $volatileEventStoreSubscription = null;
        $innerSubscriptionDrop = null;

        $this->connection->handleSubscribeToStream(
            function ($stream, $raise, $drop) use (&$innerSubscriptionDrop, &$volatileEventStoreSubscription): VolatileEventStoreSubscription {
                $innerSubscriptionDrop = $drop;

                return $volatileEventStoreSubscription = $this->createVolatileSubscription($raise, $drop, null);
            }
        );

        $this->subscription->start();
        $this->assertCount(0, $this->raisedEvents);
        $this->assertNotNull($innerSubscriptionDrop);

        $innerSubscriptionDrop($volatileEventStoreSubscription, SubscriptionDropReason::ConnectionClosed, null);

        $result = $this->dropEvent->getFuture()->await(new TimeoutCancellation(self::Timeout));
        $this->assertTrue($result);

        $this->dropEvent = new DeferredFuture();
        $waitForOutOfOrderEvent = new DeferredFuture();

        $callCount = 0;

        $this->connection->handleReadStreamEventsForward(
            function ($stream, $start, $max) use (&$callCount, $waitForOutOfOrderEvent): StreamEventsSlice {
                $callCount++;

                if (1 === $callCount) {
                    return $this->createStreamEventsSlice(0, 0, true);
                }

                if (2 === $callCount) {
                    $result = $waitForOutOfOrderEvent->getFuture()->await(new TimeoutCancellation(self::Timeout));

                    $this->assertTrue($result);

                    return $this->createStreamEventsSlice(0, 1, true);
                }
            }
        );

        $event1 = new ResolvedEvent(
            new RecordedEvent(
                self::StreamId,
                1,
                EventId::generate(),
                'test-event',
                false,
                '',
                '',
                DateTime::utcNow()
            ),
            null,
            null
        );

        $this->connection->handleSubscribeToStream(
            function ($stream, $raise, $drop) use ($event1, $volatileEventStoreSubscription): VolatileEventStoreSubscription {
                $volatileEventStoreSubscription2 = $this->createVolatileSubscription($raise, $drop, null);
                $raise($volatileEventStoreSubscription2, $event1);

                return $volatileEventStoreSubscription;
            }
        );

        $reconnectDeferred = new DeferredFuture();
        EventLoop::defer(function () use ($reconnectDeferred): void {
            $this->connection->onConnected2(new ClientConnectionEventArgs(
                $this->connection,
                new EndPoint('0.0.0.0', 1)
            ));

            $reconnectDeferred->complete(true);
        });

        $waitForOutOfOrderEvent->complete(true);

        delay(0.25); // wait for subscription to do its job

        $result = $this->raisedEventEvent->getFuture()->await(new TimeoutCancellation(self::Timeout));

        $this->assertTrue($result);

        $this->assertSame(0, $this->raisedEvents[0]->originalEventNumber());
        $this->assertSame(1, $this->raisedEvents[1]->originalEventNumber());

        $result = $reconnectDeferred->getFuture()->await(new TimeoutCancellation(self::Timeout));
        $this->assertTrue($result);
    }

    private function createVolatileSubscription(
        callable $raise,
        callable $drop,
        ?int $lastEventNumber
    ): VolatileEventStoreSubscription {
        return new VolatileEventStoreSubscription(
            new VolatileSubscriptionOperation(
                new NullLogger(),
                new DeferredFuture(),
                self::StreamId,
                false,
                null,
                fn () => function (
                    EventStoreSubscription $subscription,
                    ResolvedEvent $resolvedEvent
                ) use ($raise): void {
                    $raise($subscription, $resolvedEvent);
                },
                fn () => function (
                    EventStoreSubscription $subscription,
                    SubscriptionDropReason $reason,
                    ?Throwable $exception = null
                ) use ($drop): void {
                    $drop($subscription, $reason, $exception);
                },
                false,
                fn () => null
            ),
            self::StreamId,
            -1,
            $lastEventNumber
        );
    }

    private function createStreamEventsSlice(
        int $fromEvent = 0,
        int $count = 1,
        bool $isEnd = false
    ): StreamEventsSlice {
        $events = [];

        for ($i = 0; $i < $count; $i++) {
            $events[] = new ResolvedEvent(
                new RecordedEvent(
                    self::StreamId,
                    $i,
                    EventId::generate(),
                    'test-event',
                    false,
                    '',
                    '',
                    DateTime::utcNow()
                ),
                null,
                null
            );
        }

        return new StreamEventsSlice(
            SliceReadStatus::Success,
            self::StreamId,
            $fromEvent,
            ReadDirection::Forward,
            $events,
            $fromEvent + $count,
            100,
            $isEnd
        );
    }
}
