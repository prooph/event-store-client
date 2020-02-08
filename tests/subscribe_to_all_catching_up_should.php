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
use Amp\Delayed;
use Amp\Promise;
use Amp\Success;
use Closure;
use Exception;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\AllEventsSlice;
use Prooph\EventStore\Async\CatchUpSubscriptionDropped;
use Prooph\EventStore\Async\EventAppearedOnCatchupSubscription;
use Prooph\EventStore\Async\EventAppearedOnSubscription;
use Prooph\EventStore\Async\EventStoreCatchUpSubscription;
use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\CatchUpSubscriptionSettings;
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\Common\SystemStreams;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventStoreSubscription;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Position;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\SubscriptionDropReason;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\Internal\EventStoreAllCatchUpSubscription;
use Prooph\EventStoreClient\Internal\ManualResetEventSlim;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;

class subscribe_to_all_catching_up_should extends TestCase
{
    private const TIMEOUT = 10000;

    private EventStoreConnection $conn;

    /**
     * @throws Throwable
     */
    private function execute(Closure $function): void
    {
        Promise\wait(call(function () use ($function): Generator {
            $this->conn = TestConnection::create();

            yield $this->conn->connectAsync();

            yield $this->conn->setStreamMetadataAsync(
                '$all',
                ExpectedVersion::ANY,
                StreamMetadata::create()->setReadRoles(SystemRoles::ALL)->build(),
                new UserCredentials(SystemUsers::ADMIN, SystemUsers::DEFAULT_ADMIN_PASSWORD)
            );

            yield from $function();

            yield $this->conn->setStreamMetadataAsync(
                '$all',
                ExpectedVersion::ANY,
                StreamMetadata::create()->build(),
                new UserCredentials(SystemUsers::ADMIN, SystemUsers::DEFAULT_ADMIN_PASSWORD)
            );

            $this->conn->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function call_dropped_callback_after_stop_method_call(): void
    {
        $this->execute(function () {
            $store = TestConnection::create();

            yield $store->connectAsync();

            $dropped = new CountdownEvent(1);

            $subscription = yield $store->subscribeToAllFromAsync(
                null,
                CatchUpSubscriptionSettings::default(),
                new class() implements EventAppearedOnCatchupSubscription {
                    public function __invoke(
                        EventStoreCatchUpSubscription $subscription,
                        ResolvedEvent $resolvedEvent
                    ): Promise {
                        return new Success();
                    }
                },
                null,
                new class($dropped) implements CatchUpSubscriptionDropped {
                    private CountdownEvent $dropped;

                    public function __construct(CountdownEvent $dropped)
                    {
                        $this->dropped = $dropped;
                    }

                    public function __invoke(
                        EventStoreCatchUpSubscription $subscription,
                        SubscriptionDropReason $reason,
                        ?Throwable $exception = null
                    ): void {
                        $this->dropped->signal();
                    }
                }
            );
            \assert($subscription instanceof EventStoreAllCatchUpSubscription);

            $this->assertFalse(yield $dropped->wait(0));
            yield $subscription->stop(self::TIMEOUT);
            $this->assertTrue(yield $dropped->wait(self::TIMEOUT));
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function call_dropped_callback_when_an_error_occurs_while_processing_an_event(): void
    {
        $this->execute(function () {
            $stream = 'all_call_dropped_callback_when_an_error_occurs_while_processing_an_event';

            $store = TestConnection::create();

            yield $store->connectAsync();

            yield $store->appendToStreamAsync(
                $stream,
                ExpectedVersion::ANY,
                [TestEvent::newTestEvent()]
            );

            $dropped = new CountdownEvent(1);

            $subscription = yield $store->subscribeToAllFromAsync(
                null,
                CatchUpSubscriptionSettings::default(),
                new class() implements EventAppearedOnCatchupSubscription {
                    public function __invoke(
                        EventStoreCatchUpSubscription $subscription,
                        ResolvedEvent $resolvedEvent
                    ): Promise {
                        throw new Exception('Error');
                    }
                },
                null,
                new class($dropped) implements CatchUpSubscriptionDropped {
                    private CountdownEvent $dropped;

                    public function __construct(CountdownEvent $dropped)
                    {
                        $this->dropped = $dropped;
                    }

                    public function __invoke(
                        EventStoreCatchUpSubscription $subscription,
                        SubscriptionDropReason $reason,
                        ?Throwable $exception = null
                    ): void {
                        $this->dropped->signal();
                    }
                }
            );
            \assert($subscription instanceof EventStoreAllCatchUpSubscription);

            yield $subscription->stop(self::TIMEOUT);

            $this->assertTrue(yield $dropped->wait(self::TIMEOUT));
        });
    }

    /**
     * No way to guarantee an empty db
     *
     * @test
     * @throws Throwable
     * @group ignore
     */
    public function be_able_to_subscribe_to_empty_db(): void
    {
        $this->execute(function () {
            $store = TestConnection::create();

            yield $store->connectAsync();

            $appeared = new ManualResetEventSlim();
            $dropped = new CountdownEvent(1);

            $subscription = yield $store->subscribeToAllFromAsync(
                null,
                CatchUpSubscriptionSettings::default(),
                new class($appeared) implements EventAppearedOnCatchupSubscription {
                    private ManualResetEventSlim $appeared;

                    public function __construct(ManualResetEventSlim $appeared)
                    {
                        $this->appeared = $appeared;
                    }

                    public function __invoke(
                        EventStoreCatchUpSubscription $subscription,
                        ResolvedEvent $resolvedEvent
                    ): Promise {
                        if (! SystemStreams::isSystemStream($resolvedEvent->originalEvent()->eventStreamId())) {
                            $this->appeared->set();
                        }

                        return new Success();
                    }
                },
                null,
                new class($dropped) implements CatchUpSubscriptionDropped {
                    private CountdownEvent $dropped;

                    public function __construct(CountdownEvent $dropped)
                    {
                        $this->dropped = $dropped;
                    }

                    public function __invoke(
                        EventStoreCatchUpSubscription $subscription,
                        SubscriptionDropReason $reason,
                        ?Throwable $exception = null
                    ): void {
                        $this->dropped->signal();
                    }
                }
            );
            \assert($subscription instanceof EventStoreAllCatchUpSubscription);

            yield new Delayed(5000); // give time for first pull phase

            yield $store->subscribeToAllAsync(
                false,
                new class() implements EventAppearedOnSubscription {
                    public function __invoke(
                        EventStoreSubscription $subscription,
                        ResolvedEvent $resolvedEvent
                    ): Promise {
                        return new Success();
                    }
                }
            );

            yield new Delayed(5000);

            $this->assertFalse(yield $appeared->wait(0), 'Some event appeared');
            $this->assertFalse(yield $dropped->wait(0), 'Subscription was dropped prematurely');

            yield $subscription->stop(self::TIMEOUT);

            $this->assertTrue(yield $dropped->wait(self::TIMEOUT));
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function read_all_existing_events_and_keep_listening_to_new_ones(): void
    {
        $this->execute(function () {
            $store = TestConnection::create();

            yield $store->connectAsync();

            $result = yield $this->conn->readAllEventsBackwardAsync(Position::end(), 1, false);
            \assert($result instanceof AllEventsSlice);
            $position = $result->nextPosition();

            $events = [];
            $appeared = new CountdownEvent(20);
            $dropped = new CountdownEvent(1);

            for ($i = 0; $i < 10; $i++) {
                yield $store->appendToStreamAsync(
                    'all_read_all_existing_events_and_keep_listening_to_new_ones-' . $i,
                    -1,
                    [new EventData(null, 'et-' . $i, false)]
                );
            }

            $subscription = yield $store->subscribeToAllFromAsync(
                $position,
                CatchUpSubscriptionSettings::default(),
                new class($events, $appeared) implements EventAppearedOnCatchupSubscription {
                    private array $events;
                    private CountdownEvent $appeared;

                    public function __construct(array &$events, CountdownEvent $appeared)
                    {
                        $this->events = &$events;
                        $this->appeared = $appeared;
                    }

                    public function __invoke(
                        EventStoreCatchUpSubscription $subscription,
                        ResolvedEvent $resolvedEvent
                    ): Promise {
                        if (! SystemStreams::isSystemStream($resolvedEvent->originalEvent()->eventStreamId())) {
                            $this->events[] = $resolvedEvent;
                            $this->appeared->signal();
                        }

                        return new Success();
                    }
                },
                null,
                new class($dropped) implements CatchUpSubscriptionDropped {
                    private CountdownEvent $dropped;

                    public function __construct(CountdownEvent $dropped)
                    {
                        $this->dropped = $dropped;
                    }

                    public function __invoke(
                        EventStoreCatchUpSubscription $subscription,
                        SubscriptionDropReason $reason,
                        ?Throwable $exception = null
                    ): void {
                        $this->dropped->signal();
                    }
                }
            );
            \assert($subscription instanceof EventStoreAllCatchUpSubscription);

            for ($i = 10; $i < 20; $i++) {
                yield $store->appendToStreamAsync(
                    'all_read_all_existing_events_and_keep_listening_to_new_ones-' . $i,
                    -1,
                    [new EventData(null, 'et-' . $i, false)]
                );
            }

            if (! yield $appeared->wait(self::TIMEOUT)) {
                $this->assertFalse(yield $dropped->wait(0), 'Subscription was dropped prematurely');
                $this->fail('Could not wait for all events');

                return;
            }

            $this->assertCount(20, $events);

            for ($i = 0; $i < 20; $i++) {
                $this->assertSame('et-' . $i, $events[$i]->originalEvent()->eventType());
            }

            $this->assertFalse(yield $dropped->wait(0));
            yield $subscription->stop(self::TIMEOUT);
            $this->assertTrue(yield $dropped->wait(self::TIMEOUT));
        });
    }

    /**
     * Not working against single db
     *
     * @test
     * @throws Throwable
     * @group ignore
     */
    public function filter_events_and_keep_listening_to_new_ones(): void
    {
        $this->execute(function () {
            $store = TestConnection::create();

            yield $store->connectAsync();

            $events = [];
            $appeared = new CountdownEvent(10);
            $dropped = new CountdownEvent(1);

            for ($i = 0; $i < 10; $i++) {
                yield $store->appendToStreamAsync(
                    'all_filter_events_and_keep_listening_to_new_ones-' . $i,
                    -1,
                    [new EventData(null, 'et-' . $i, false)]
                );
            }

            $allSlice = yield $store->readAllEventsForwardAsync(Position::start(), 100, false);
            \assert($allSlice instanceof AllEventsSlice);
            $lastEvent = \array_values(\array_slice($allSlice->events(), -1))[0];
            \assert($lastEvent instanceof ResolvedEvent);

            $subscription = yield $store->subscribeToAllFromAsync(
                $lastEvent->originalPosition(),
                CatchUpSubscriptionSettings::default(),
                new class($events, $appeared) implements EventAppearedOnCatchupSubscription {
                    private array $events;
                    private CountdownEvent $appeared;

                    public function __construct(array &$events, CountdownEvent $appeared)
                    {
                        $this->events = &$events;
                        $this->appeared = $appeared;
                    }

                    public function __invoke(
                        EventStoreCatchUpSubscription $subscription,
                        ResolvedEvent $resolvedEvent
                    ): Promise {
                        if (! SystemStreams::isSystemStream($resolvedEvent->originalEvent()->eventStreamId())) {
                            $this->events[] = $resolvedEvent;
                            $this->appeared->signal();
                        }

                        return new Success();
                    }
                },
                null,
                new class($dropped) implements CatchUpSubscriptionDropped {
                    private CountdownEvent $dropped;

                    public function __construct(CountdownEvent $dropped)
                    {
                        $this->dropped = $dropped;
                    }

                    public function __invoke(
                        EventStoreCatchUpSubscription $subscription,
                        SubscriptionDropReason $reason,
                        ?Throwable $exception = null
                    ): void {
                        $this->dropped->signal();
                    }
                }
            );
            \assert($subscription instanceof EventStoreAllCatchUpSubscription);

            for ($i = 10; $i < 20; $i++) {
                yield $store->appendToStreamAsync(
                    'all_filter_events_and_keep_listening_to_new_ones-' . $i,
                    -1,
                    [new EventData(null, 'et-' . $i, false)]
                );
            }

            if (! yield $appeared->wait(self::TIMEOUT)) {
                $this->assertFalse(yield $dropped->wait(0), 'Subscription was dropped prematurely');
                $this->fail('Could not wait for all events');

                return;
            }

            $this->assertCount(10, $events);

            for ($i = 0; $i < 10; $i++) {
                $this->assertSame('et-' . (10 + $i), $events[$i]->originalEvent()->eventType());
            }

            $this->assertFalse(yield $dropped->wait(0));
            yield $subscription->stop();
            $this->assertTrue(yield $dropped->wait(self::TIMEOUT));

            $lastEvent = \array_values(\array_slice($events, -1))[0];
            \assert($lastEvent instanceof ResolvedEvent);
            $this->assertTrue($lastEvent->originalPosition()->equals($subscription->lastProcessedPosition()));
        });
    }

    /**
     * Not working against single db
     *
     * @test
     * @throws Throwable
     * @group ignore
     */
    public function filter_events_and_work_if_nothing_was_written_after_subscription(): void
    {
        $this->execute(function () {
            $store = TestConnection::create();

            yield $store->connectAsync();

            /** @var ResolvedEvent[] $events */
            $events = [];
            $appeared = new CountdownEvent(1);
            $dropped = new CountdownEvent(1);

            for ($i = 0; $i < 10; $i++) {
                yield $store->appendToStreamAsync(
                    'all_filter_events_and_work_if_nothing_was_written_after_subscription-' . $i,
                    -1,
                    [new EventData(null, 'et-' . $i, false)]
                );
            }

            $allSlice = yield $store->readAllEventsBackwardAsync(Position::end(), 2, false);
            \assert($allSlice instanceof AllEventsSlice);
            $lastEvent = $allSlice->events()[1];

            $subscription = yield $store->subscribeToAllFromAsync(
                $lastEvent->originalPosition(),
                CatchUpSubscriptionSettings::default(),
                new class($events, $appeared) implements EventAppearedOnCatchupSubscription {
                    private array $events;
                    private CountdownEvent $appeared;

                    public function __construct(array &$events, CountdownEvent $appeared)
                    {
                        $this->events = &$events;
                        $this->appeared = $appeared;
                    }

                    public function __invoke(
                        EventStoreCatchUpSubscription $subscription,
                        ResolvedEvent $resolvedEvent
                    ): Promise {
                        $this->events[] = $resolvedEvent;
                        $this->appeared->signal();

                        return new Success();
                    }
                },
                null,
                new class($dropped) implements CatchUpSubscriptionDropped {
                    private CountdownEvent $dropped;

                    public function __construct(CountdownEvent $dropped)
                    {
                        $this->dropped = $dropped;
                    }

                    public function __invoke(
                        EventStoreCatchUpSubscription $subscription,
                        SubscriptionDropReason $reason,
                        ?Throwable $exception = null
                    ): void {
                        $this->dropped->signal();
                    }
                }
            );
            \assert($subscription instanceof EventStoreAllCatchUpSubscription);

            if (! yield $appeared->wait(self::TIMEOUT)) {
                $this->assertFalse(yield $dropped->wait(0), 'Subscription was dropped prematurely');
                $this->fail('Could not wait for all events');

                return;
            }

            $this->assertCount(1, $events);
            $this->assertSame('et-9', $events[0]->originalEvent()->eventType());

            $this->assertFalse(yield $dropped->wait(0));
            yield $subscription->stop(self::TIMEOUT);
            $this->assertTrue(yield $dropped->wait(self::TIMEOUT));

            $this->assertTrue($events[0]->originalPosition()->equals($subscription->lastProcessedPosition()));
        });
    }
}
