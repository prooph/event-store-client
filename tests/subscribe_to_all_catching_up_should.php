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

namespace ProophTest\EventStoreClient;

use Amp\Delayed;
use Amp\Promise;
use Amp\Success;
use Exception;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\AllEventsSlice;
use Prooph\EventStoreClient\CatchUpSubscriptionDropped;
use Prooph\EventStoreClient\CatchUpSubscriptionSettings;
use Prooph\EventStoreClient\Common\SystemRoles;
use Prooph\EventStoreClient\Common\SystemStreams;
use Prooph\EventStoreClient\EventAppearedOnCatchupSubscription;
use Prooph\EventStoreClient\EventAppearedOnSubscription;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\EventStoreAsyncConnection;
use Prooph\EventStoreClient\EventStoreSubscription;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\Internal\EventStoreAllCatchUpSubscription;
use Prooph\EventStoreClient\Internal\EventStoreCatchUpSubscription;
use Prooph\EventStoreClient\Internal\ManualResetEventSlim;
use Prooph\EventStoreClient\Internal\ResolvedEvent;
use Prooph\EventStoreClient\Position;
use Prooph\EventStoreClient\StreamMetadata;
use Prooph\EventStoreClient\SubscriptionDropReason;
use Prooph\EventStoreClient\UserCredentials;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;
use function Amp\call;

class subscribe_to_all_catching_up_should extends TestCase
{
    private const TIMEOUT = 10000;

    /** @var EventStoreAsyncConnection */
    private $conn;

    /**
     * @throws Throwable
     */
    private function execute(callable $function): void
    {
        Promise\wait(call(function () use ($function): Generator {
            $this->conn = TestConnection::createAsync();

            yield $this->conn->connectAsync();

            yield $this->conn->setStreamMetadataAsync(
                '$all',
                ExpectedVersion::ANY,
                StreamMetadata::build()->setReadRoles(SystemRoles::ALL)->build(),
                new UserCredentials(SystemUsers::ADMIN, SystemUsers::DEFAULT_ADMIN_PASSWORD)
            );

            yield from $function();

            yield $this->conn->setStreamMetadataAsync(
                '$all',
                ExpectedVersion::ANY,
                StreamMetadata::build()->build(),
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
            $store = TestConnection::createAsync();

            yield $store->connectAsync();

            $dropped = new CountdownEvent(1);

            /** @var EventStoreAllCatchUpSubscription $subscription */
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
                    /** @var CountdownEvent */
                    private $dropped;

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

            $store = TestConnection::createAsync();

            yield $store->connectAsync();

            yield $store->appendToStreamAsync(
                $stream,
                ExpectedVersion::ANY,
                [TestEvent::new()]
            );

            $dropped = new CountdownEvent(1);

            /** @var EventStoreAllCatchUpSubscription $subscription */
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
                    /** @var CountdownEvent */
                    private $dropped;

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
            $store = TestConnection::createAsync();

            yield $store->connectAsync();

            $appeared = new ManualResetEventSlim();
            $dropped = new CountdownEvent(1);

            /** @var EventStoreAllCatchUpSubscription $subscription */
            $subscription = yield $store->subscribeToAllFromAsync(
                null,
                CatchUpSubscriptionSettings::default(),
                new class($appeared) implements EventAppearedOnCatchupSubscription {
                    /** @var ManualResetEventSlim */
                    private $appeared;

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
                    /** @var CountdownEvent */
                    private $dropped;

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
            $store = TestConnection::createAsync();

            yield $store->connectAsync();

            /** @var AllEventsSlice $result */
            $result = yield $this->conn->readAllEventsBackwardAsync(Position::end(), 1, false);
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

            /** @var EventStoreAllCatchUpSubscription $subscription */
            $subscription = yield $store->subscribeToAllFromAsync(
                $position,
                CatchUpSubscriptionSettings::default(),
                new class($events, $appeared) implements EventAppearedOnCatchupSubscription {
                    /** @var array */
                    private $events;
                    /** @var CountdownEvent */
                    private $appeared;

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
                    /** @var CountdownEvent */
                    private $dropped;

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
            $store = TestConnection::createAsync();

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

            /** @var AllEventsSlice $allSlice */
            $allSlice = yield $store->readAllEventsForwardAsync(Position::start(), 100, false);
            /** @var ResolvedEvent $lastEvent */
            $lastEvent = \array_values(\array_slice($allSlice->events(), -1))[0];

            /** @var EventStoreAllCatchUpSubscription $subscription */
            $subscription = yield $store->subscribeToAllFromAsync(
                $lastEvent->originalPosition(),
                CatchUpSubscriptionSettings::default(),
                new class($events, $appeared) implements EventAppearedOnCatchupSubscription {
                    /** @var array */
                    private $events;
                    /** @var CountdownEvent */
                    private $appeared;

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
                            //\var_dump($resolvedEvent->originalEvent()->eventStreamId());
                            $this->events[] = $resolvedEvent;
                            $this->appeared->signal();
                        }

                        return new Success();
                    }
                },
                null,
                new class($dropped) implements CatchUpSubscriptionDropped {
                    /** @var CountdownEvent */
                    private $dropped;

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

            /** @var ResolvedEvent $lastEvent */
            $lastEvent = \array_values(\array_slice($events, -1))[0];
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
            $store = TestConnection::createAsync();

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

            /** @var AllEventsSlice $allSlice */
            $allSlice = yield $store->readAllEventsBackwardAsync(Position::end(), 2, false);
            $lastEvent = $allSlice->events()[1];

            /** @var EventStoreAllCatchUpSubscription $subscription */
            $subscription = yield $store->subscribeToAllFromAsync(
                $lastEvent->originalPosition(),
                CatchUpSubscriptionSettings::default(),
                new class($events, $appeared) implements EventAppearedOnCatchupSubscription {
                    /** @var array */
                    private $events;
                    /** @var CountdownEvent */
                    private $appeared;

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
                    /** @var CountdownEvent */
                    private $dropped;

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
