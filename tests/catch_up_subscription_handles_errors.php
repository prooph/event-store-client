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

namespace ProophTest\EventStoreClient;

use function Amp\call;
use Amp\Deferred;
use Amp\Delayed;
use Amp\Failure;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use Exception;
use Generator;
use Prooph\EventStore\Async\ClientConnectionEventArgs;
use Prooph\EventStore\Async\EventStoreCatchUpSubscription;
use Prooph\EventStore\CatchUpSubscriptionSettings;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\EventId;
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
use Throwable;

class catch_up_subscription_handles_errors extends AsyncTestCase
{
    private static int $timeoutMs = 2000;
    private FakeEventStoreConnection $connection;
    private array $raisedEvents;
    private bool $liveProcessingStarted;
    private bool $isDropped;
    private Deferred $dropEvent;
    private Deferred $raisedEventEvent;
    private ?Throwable $dropException;
    private SubscriptionDropReason $dropReason;
    private EventStoreStreamCatchUpSubscription $subscription;
    private static string $streamId = 'stream1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = new FakeEventStoreConnection();
        $this->raisedEvents = [];
        $this->dropEvent = new Deferred();
        $this->raisedEventEvent = new Deferred();
        $this->liveProcessingStarted = false;
        $this->isDropped = false;
        $this->dropReason = SubscriptionDropReason::unknown();
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
            self::$streamId,
            null,
            null,
            function (
                EventStoreCatchUpSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ) use (&$props1): Promise {
                $props1['raisedEvents'][] = $resolvedEvent;
                try {
                    $props1['raisedEventEvent']->resolve(true);
                } catch (Throwable $e) {
                    // on connection close this event may appear twice, just ignore second occurrence
                }

                return new Success();
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
                $props3['dropEvent']->resolve(true);
            },
            $settings
        );
    }

    /** @test */
    public function read_events_til_stops_subscription_when_throws_immediately(): void
    {
        $expectedException = new Exception('Test');

        $this->connection->handleReadStreamEventsForwardAsync(
            function ($stream, $start, $max) use ($expectedException): Promise {
                $this->assertSame(self::$streamId, $stream);
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
            $promise = $this->subscription->startAsync();
            $promise = Promise\timeout($promise, self::$timeoutMs);

            Promise\wait($promise);
        } catch (Throwable $e) {
            $this->assertTrue($this->isDropped);
            $this->assertTrue($this->dropReason->equals(SubscriptionDropReason::catchUpError()));
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

        $this->connection->handleReadStreamEventsForwardAsync(
            function ($stream, $start, $max) use ($expectedException): Promise {
                $this->assertSame(self::$streamId, $stream);
                $this->assertSame(0, $start);
                $this->assertSame(1, $max);

                return new Failure($expectedException);
            }
        );

        $this->assertStartFailsAndDropsSubscriptionWithException($expectedException);
    }

    /** @test */
    public function read_events_til_stops_subscription_when_second_read_throws_immediately(): void
    {
        $expectedException = new Exception('Test');

        $callCount = 0;

        $this->connection->handleReadStreamEventsForwardAsync(
            function ($stream, $start, $max) use (&$callCount, $expectedException): Promise {
                $this->assertSame(self::$streamId, $stream);
                $this->assertSame(1, $max);

                if ($callCount++ === 0) {
                    $this->assertSame(0, $start);

                    return new Success($this->createStreamEventsSlice());
                }

                $this->assertSame(1, $start);

                return new Failure($expectedException);
            }
        );

        $this->assertStartFailsAndDropsSubscriptionWithException($expectedException);

        $this->assertCount(1, $this->raisedEvents);
    }

    /** @test */
    public function start_stops_subscription_if_subscribe_fails_immediately(): void
    {
        $expectedException = new Exception('Test');

        $this->connection->handleReadStreamEventsForwardAsync(
            fn ($stream, $start, $max): Promise => new Success($this->createStreamEventsSlice(0, 1, true))
        );

        $this->connection->handleSubscribeToStreamAsync(
            function ($stream, $raise, $drop) use ($expectedException): void {
                $this->assertSame(self::$streamId, $stream);

                throw $expectedException;
            }
        );

        $this->assertStartFailsAndDropsSubscriptionWithException($expectedException);
        $this->assertCount(1, $this->raisedEvents);
    }

    /** @test */
    public function start_stops_subscription_if_subscribe_fails_async(): void
    {
        $expectedException = new Exception('Test');

        $this->connection->handleReadStreamEventsForwardAsync(
            fn ($stream, $start, $max): Promise => new Success($this->createStreamEventsSlice(0, 1, true))
        );

        $this->connection->handleSubscribeToStreamAsync(
            fn ($stream, $raise, $drop): Promise => new Failure($expectedException)
        );

        $this->assertStartFailsAndDropsSubscriptionWithException($expectedException);
        $this->assertCount(1, $this->raisedEvents);
    }

    /** @test */
    public function start_stops_subscription_if_historical_missed_events_load_fails_immediate(): void
    {
        $expectedException = new Exception('Test');

        $callCount = 0;

        $this->connection->handleReadStreamEventsForwardAsync(
            function ($stream, $start, $max) use (&$callCount, $expectedException): Promise {
                if ($callCount++ === 0) {
                    $this->assertSame(0, $start);

                    return new Success($this->createStreamEventsSlice(0, 1, true));
                }

                $this->assertSame(1, $start);

                throw $expectedException;
            }
        );

        $this->connection->handleSubscribeToStreamAsync(
            fn ($stream, $raise, $drop): Promise => new Success($this->createVolatileSubscription($raise, $drop, 1))
        );

        $this->assertStartFailsAndDropsSubscriptionWithException($expectedException);
        $this->assertCount(1, $this->raisedEvents);
    }

    /** @test */
    public function start_stops_subscription_if_historical_missed_events_load_fails_async(): void
    {
        $expectedException = new Exception('Test');

        $callCount = 0;

        $this->connection->handleReadStreamEventsForwardAsync(
            function ($stream, $start, $max) use (&$callCount, $expectedException): Promise {
                if ($callCount++ === 0) {
                    $this->assertSame(0, $start);

                    return new Success($this->createStreamEventsSlice(0, 1, true));
                }

                $this->assertSame(1, $start);

                return new Failure($expectedException);
            }
        );

        $this->connection->handleSubscribeToStreamAsync(
            fn ($stream, $raise, $drop): Promise => new Success($this->createVolatileSubscription($raise, $drop, 1))
        );

        $this->assertStartFailsAndDropsSubscriptionWithException($expectedException);
        $this->assertCount(1, $this->raisedEvents);
    }

    /** @test */
    public function start_completes_onces_subscription_is_live(): Generator
    {
        $finalEvent = new Deferred();

        $callCount = 0;

        $this->connection->handleReadStreamEventsForwardAsync(
            function ($stream, $start, $max) use (&$callCount, $finalEvent): Promise {
                $callCount++;

                $result = null;

                if (1 === $callCount) {
                    return new Success($this->createStreamEventsSlice(0, 1, true));
                }

                if (2 === $callCount) {
                    $promise = Promise\timeout($finalEvent->promise(), self::$timeoutMs);

                    $promise->onResolve(function ($ex, $result) {
                        $this->assertTrue($result);
                    });

                    return new Success($this->createStreamEventsSlice(1, 1, true));
                }

                $this->fail('Should not happen');
            }
        );

        $this->connection->handleSubscribeToStreamAsync(
            fn ($stream, $raise, $drop): Promise => new Success($this->createVolatileSubscription($raise, $drop, 1))
        );

        $promise = $this->subscription->startAsync();

        $finalEvent->resolve(true);

        $this->assertNotNull(yield Promise\timeout($promise, self::$timeoutMs));
    }

    /** @test */
    public function when_live_processing_and_disconnected_reconnect_keeps_events_ordered(): Generator
    {
        $callCount = 0;

        $this->connection->handleReadStreamEventsForwardAsync(
            function ($stream, $start, $max) use (&$callCount): Promise {
                $callCount++;

                if (1 === $callCount) {
                    return new Success($this->createStreamEventsSlice(0, 0, true));
                }

                if (2 === $callCount) {
                    return new Success($this->createStreamEventsSlice(0, 0, true));
                }

                return new Failure(new Exception('Error'));
            }
        );

        $volatileEventStoreSubscription = null;
        $innerSubscriptionDrop = null;

        $this->connection->handleSubscribeToStreamAsync(
            function ($stream, $raise, $drop) use (&$innerSubscriptionDrop, &$volatileEventStoreSubscription): Promise {
                $innerSubscriptionDrop = $drop;
                $volatileEventStoreSubscription = $this->createVolatileSubscription($raise, $drop, null);
                \assert($volatileEventStoreSubscription instanceof VolatileEventStoreSubscription);

                return new Success($volatileEventStoreSubscription);
            }
        );

        $this->assertNotNull(yield Promise\timeout($this->subscription->startAsync(), self::$timeoutMs));
        $this->assertCount(0, $this->raisedEvents);
        $this->assertNotNull($innerSubscriptionDrop);

        $innerSubscriptionDrop($volatileEventStoreSubscription, SubscriptionDropReason::connectionClosed(), null);

        $result = yield Promise\timeout($this->dropEvent->promise(), self::$timeoutMs);
        $this->assertTrue($result);

        $this->dropEvent = new Deferred();
        $waitForOutOfOrderEvent = new Deferred();

        $callCount = 0;

        $this->connection->handleReadStreamEventsForwardAsync(
            function ($stream, $start, $max) use (&$callCount, $waitForOutOfOrderEvent): Promise {
                return call(function () use (&$callCount, $waitForOutOfOrderEvent): Generator {
                    $callCount++;

                    if (1 === $callCount) {
                        return yield new Success($this->createStreamEventsSlice(0, 0, true));
                    }

                    if (2 === $callCount) {
                        $result = yield Promise\timeout($waitForOutOfOrderEvent->promise(), self::$timeoutMs);

                        $this->assertTrue($result);

                        return yield new Success($this->createStreamEventsSlice(0, 1, true));
                    }
                });
            }
        );

        $event1 = new ResolvedEvent(
            new RecordedEvent(
                self::$streamId,
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

        $this->connection->handleSubscribeToStreamAsync(
            fn ($stream, $raise, $drop): Promise => call(function () use ($raise, $drop, $event1, $volatileEventStoreSubscription): Generator {
                $volatileEventStoreSubscription2 = $this->createVolatileSubscription($raise, $drop, null);
                yield $raise($volatileEventStoreSubscription2, $event1);

                return yield new Success($volatileEventStoreSubscription);
            })
        );

        $reconnectDeferred = new Deferred();
        Loop::defer(function () use ($reconnectDeferred): void {
            $this->connection->onConnected2(new ClientConnectionEventArgs(
                $this->connection,
                new EndPoint('0.0.0.0', 1)
            ));

            $reconnectDeferred->resolve(true);
        });

        $waitForOutOfOrderEvent->resolve(true);

        yield new Delayed(250); // wait for subscription to do its job

        $result = yield Promise\timeout($this->raisedEventEvent->promise(), self::$timeoutMs);

        $this->assertTrue($result);

        $this->assertSame(0, $this->raisedEvents[0]->originalEventNumber());
        $this->assertSame(1, $this->raisedEvents[1]->originalEventNumber());

        $result = yield Promise\timeout($reconnectDeferred->promise(), self::$timeoutMs);
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
                new Deferred(),
                self::$streamId,
                false,
                null,
                fn () => function (
                    EventStoreSubscription $subscription,
                    ResolvedEvent $resolvedEvent
                ) use ($raise): Promise {
                    return $raise($subscription, $resolvedEvent);
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
            self::$streamId,
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
                    self::$streamId,
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
            SliceReadStatus::success(),
            self::$streamId,
            $fromEvent,
            ReadDirection::forward(),
            $events,
            $fromEvent + $count,
            100,
            $isEnd
        );
    }
}
