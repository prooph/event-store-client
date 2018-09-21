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

namespace ProophTest\EventStoreClient;

use Amp\Deferred;
use Amp\Delayed;
use Amp\Failure;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;
use Exception;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\CatchUpSubscriptionDropped;
use Prooph\EventStoreClient\CatchUpSubscriptionSettings;
use Prooph\EventStoreClient\ClientConnectionEventArgs;
use Prooph\EventStoreClient\ClientOperations\VolatileSubscriptionOperation;
use Prooph\EventStoreClient\EndPoint;
use Prooph\EventStoreClient\EventAppearedOnCatchupSubscription;
use Prooph\EventStoreClient\EventAppearedOnSubscription;
use Prooph\EventStoreClient\EventId;
use Prooph\EventStoreClient\EventStoreSubscription;
use Prooph\EventStoreClient\Internal\DateTimeUtil;
use Prooph\EventStoreClient\Internal\EventStoreCatchUpSubscription;
use Prooph\EventStoreClient\Internal\EventStoreStreamCatchUpSubscription;
use Prooph\EventStoreClient\Internal\ResolvedEvent;
use Prooph\EventStoreClient\Internal\VolatileEventStoreSubscription;
use Prooph\EventStoreClient\LiveProcessingStarted;
use Prooph\EventStoreClient\ReadDirection;
use Prooph\EventStoreClient\RecordedEvent;
use Prooph\EventStoreClient\SliceReadStatus;
use Prooph\EventStoreClient\StreamEventsSlice;
use Prooph\EventStoreClient\SubscriptionDropped;
use Prooph\EventStoreClient\SubscriptionDropReason;
use Psr\Log\NullLogger;
use Throwable;
use function Amp\call;

class catch_up_subscription_handles_errors extends TestCase
{
    /** @var int */
    private static $timeoutMs = 2000;
    /** @var FakeEventStoreConnection */
    private $connection;
    /** @var array|ResolvedEvent[] */
    private $raisedEvents;
    /** @var bool */
    private $liveProcessingStarted;
    /** @var bool */
    private $isDropped;
    /** @var Deferred */
    private $dropEvent;
    /** @var Deferred */
    private $raisedEventEvent;
    /** @var Throwable */
    private $dropException;
    /** @var SubscriptionDropReason */
    private $dropReason;
    /** @var EventStoreStreamCatchUpSubscription */
    private $subscription;
    /** @var string */
    private static $streamId = 'stream1';

    protected function setUp(): void
    {
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
            new class($props1) implements EventAppearedOnCatchupSubscription {
                private $props;

                public function __construct(array &$props)
                {
                    $this->props = &$props;
                }

                public function __invoke(
                    EventStoreCatchUpSubscription $subscription,
                    ResolvedEvent $resolvedEvent
                ): Promise {
                    $this->props['raisedEvents'][] = $resolvedEvent;
                    $this->props['raisedEventEvent']->resolve(true);

                    return new Success();
                }
            },
            new class($props2) implements LiveProcessingStarted {
                private $props;

                public function __construct(array &$props)
                {
                    $this->props = &$props;
                }

                public function __invoke(EventStoreCatchUpSubscription $subscription): void
                {
                    $this->props['liveProcessingStarted'] = true;
                }
            },
            new class($props3) implements CatchUpSubscriptionDropped {
                private $props;

                public function __construct(array &$props)
                {
                    $this->props = &$props;
                }

                public function __invoke(
                    EventStoreCatchUpSubscription $subscription,
                    SubscriptionDropReason $reason,
                    ?Throwable $exception = null
                ): void {
                    $this->props['isDropped'] = true;
                    $this->props['dropReason'] = $reason;
                    $this->props['dropException'] = $exception;
                    $this->props['dropEvent']->resolve(true);
                }
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
            function ($stream, $start, $max): Promise {
                return new Success($this->createStreamEventsSlice(0, 1, true));
            }
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
            function ($stream, $start, $max): Promise {
                return new Success($this->createStreamEventsSlice(0, 1, true));
            }
        );

        $this->connection->handleSubscribeToStreamAsync(
            function ($stream, $raise, $drop) use ($expectedException): Promise {
                return new Failure($expectedException);
            }
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
            function ($stream, $raise, $drop): Promise {
                return new Success($this->createVolatileSubscription($raise, $drop, 1));
            }
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
            function ($stream, $raise, $drop): Promise {
                return new Success($this->createVolatileSubscription($raise, $drop, 1));
            }
        );

        $this->assertStartFailsAndDropsSubscriptionWithException($expectedException);
        $this->assertCount(1, $this->raisedEvents);
    }

    /**
     * @test
     * @throws Throwable
     */
    public function start_completes_onces_subscription_is_live(): void
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
            function ($stream, $raise, $drop): Promise {
                return new Success($this->createVolatileSubscription($raise, $drop, 1));
            }
        );

        Promise\wait(call(function () use ($finalEvent): Generator {
            $promise = $this->subscription->startAsync();

            $finalEvent->resolve(true);

            $promise = Promise\timeout($promise, self::$timeoutMs);

            $promise->onResolve(function ($ex, $result): void {
                $this->assertTrue($result);
            });

            yield new Success();
        }));
    }

    /**
     * @test
     * @throws Throwable
     * @group by
     */
    public function when_live_processing_and_disconnected_reconnect_keeps_events_ordered(): void
    {
        Promise\wait(call(function (): Generator {
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

            /** @var VolatileEventStoreSubscription|null $volatileEventStoreSubscription */
            $volatileEventStoreSubscription = null;
            /** @var SubscriptionDropped|null $innerSubscriptionDrop */
            $innerSubscriptionDrop = null;

            $this->connection->handleSubscribeToStreamAsync(
                function ($stream, $raise, $drop) use (&$innerSubscriptionDrop, &$volatileEventStoreSubscription): Promise {
                    $innerSubscriptionDrop = $drop;
                    $volatileEventStoreSubscription = $this->createVolatileSubscription($raise, $drop, null);

                    return new Success($volatileEventStoreSubscription);
                }
            );

            $result = yield Promise\timeout($this->subscription->startAsync(), self::$timeoutMs);

            $this->assertTrue($result);

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

            $event1 = new \Prooph\EventStoreClient\ResolvedEvent(
                new RecordedEvent(
                    self::$streamId,
                    1,
                    EventId::generate(),
                    'test-event',
                    false,
                    '',
                    '',
                    DateTimeUtil::utcNow()
                ),
                null,
                null
            );

            $this->connection->handleSubscribeToStreamAsync(
                function ($stream, $raise, $drop) use ($event1, $volatileEventStoreSubscription): Promise {
                    return call(function () use ($raise, $drop, $event1, $volatileEventStoreSubscription): Generator {
                        $volatileEventStoreSubscription2 = $this->createVolatileSubscription($raise, $drop, null);
                        yield $raise($volatileEventStoreSubscription2, $event1);

                        return yield new Success($volatileEventStoreSubscription);
                    });
                }
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

            yield new Delayed(100); // wait for subscription to do its job

            $result = yield Promise\timeout($this->raisedEventEvent->promise(), self::$timeoutMs);

            $this->assertTrue($result);

            $this->assertSame(0, $this->raisedEvents[0]->originalEventNumber());
            $this->assertSame(1, $this->raisedEvents[1]->originalEventNumber());

            $result = yield Promise\timeout($reconnectDeferred->promise(), self::$timeoutMs);
            $this->assertTrue($result);
        }));
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
                new class($raise) implements EventAppearedOnSubscription {
                    private $raise;

                    public function __construct($raise)
                    {
                        $this->raise = $raise;
                    }

                    public function __invoke(
                        EventStoreSubscription $subscription,
                        ResolvedEvent $resolvedEvent
                    ): Promise {
                        return ($this->raise)($subscription, $resolvedEvent);
                    }
                },
                new class($drop) implements SubscriptionDropped {
                    private $drop;

                    public function __construct($drop)
                    {
                        $this->drop = $drop;
                    }

                    public function __invoke(
                        EventStoreSubscription $subscription,
                        SubscriptionDropReason $reason,
                        ?Throwable $exception = null
                    ): void {
                        ($this->drop)($subscription, $reason, $exception);
                    }
                },
                false,
                function () {
                    return null;
                }
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
            $events[] = new \Prooph\EventStoreClient\ResolvedEvent(
                new RecordedEvent(
                    self::$streamId,
                    $i,
                    EventId::generate(),
                    'test-event',
                    false,
                    '',
                    '',
                    DateTimeUtil::utcNow()
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
