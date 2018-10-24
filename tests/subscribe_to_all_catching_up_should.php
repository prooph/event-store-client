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

use Amp\Deferred;
use Amp\Delayed;
use Amp\Promise;
use Amp\Success;
use Amp\TimeoutException;
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

            $dropped = new Deferred();

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
                    /** @var Deferred */
                    private $dropped;

                    public function __construct(Deferred $dropped)
                    {
                        $this->dropped = $dropped;
                    }

                    public function __invoke(
                        EventStoreCatchUpSubscription $subscription,
                        SubscriptionDropReason $reason,
                        ?Throwable $exception = null
                    ): void {
                        $this->dropped->resolve(true);
                    }
                }
            );

            $subscription->stopWithTimeout(self::TIMEOUT);

            $this->assertTrue(yield Promise\timeout($dropped->promise(), self::TIMEOUT));
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

            $dropped = new Deferred();

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
                    /** @var Deferred */
                    private $dropped;

                    public function __construct(Deferred $dropped)
                    {
                        $this->dropped = $dropped;
                    }

                    public function __invoke(
                        EventStoreCatchUpSubscription $subscription,
                        SubscriptionDropReason $reason,
                        ?Throwable $exception = null
                    ): void {
                        $this->dropped->resolve(true);
                    }
                }
            );

            $subscription->stopWithTimeout(self::TIMEOUT);

            $this->assertTrue(yield Promise\timeout($dropped->promise(), self::TIMEOUT));
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function be_able_to_subscribe_to_empty_db(): void
    {
        $this->execute(function () {
            $store = TestConnection::createAsync();

            yield $store->connectAsync();

            $appeared = new Deferred();
            $dropped = new Deferred();

            /** @var EventStoreAllCatchUpSubscription $subscription */
            $subscription = yield $store->subscribeToAllFromAsync(
                null,
                CatchUpSubscriptionSettings::default(),
                new class($appeared) implements EventAppearedOnCatchupSubscription {
                    /** @var Deferred */
                    private $appeared;

                    public function __construct(Deferred $appeared)
                    {
                        $this->appeared = $appeared;
                    }

                    public function __invoke(
                        EventStoreCatchUpSubscription $subscription,
                        ResolvedEvent $resolvedEvent
                    ): Promise {
                        if (! SystemStreams::isSystemStream($resolvedEvent->originalEvent()->eventStreamId())) {
                            $this->appeared->resolve(true);
                        }

                        return new Success();
                    }
                },
                null,
                new class($dropped) implements CatchUpSubscriptionDropped {
                    /** @var Deferred */
                    private $dropped;

                    public function __construct(Deferred $dropped)
                    {
                        $this->dropped = $dropped;
                    }

                    public function __invoke(
                        EventStoreCatchUpSubscription $subscription,
                        SubscriptionDropReason $reason,
                        ?Throwable $exception = null
                    ): void {
                        $this->dropped->resolve(true);
                    }
                }
            );

            yield new Delayed(5000); // give time for first pull phase

            yield $store->subscribeToAllAsync(
                false,
                new class($appeared) implements EventAppearedOnSubscription {
                    /** @var Deferred */
                    private $appeared;

                    public function __construct(Deferred $appeared)
                    {
                        $this->appeared = $appeared;
                    }

                    public function __invoke(
                        EventStoreSubscription $subscription,
                        ResolvedEvent $resolvedEvent
                    ): Promise {
                        return new Success();
                    }
                }
            );

            yield new Delayed(5000);

            try {
                yield Promise\timeout($appeared->promise(), 0);
            } catch (TimeoutException $e) {
                $this->fail('Some event appeared');
            }

            try {
                yield Promise\timeout($dropped->promise(), 0);
            } catch (TimeoutException $e) {
                $this->fail('Subscription was dropped prematurely');
            }

            $subscription->stopWithTimeout(self::TIMEOUT);

            $this->assertTrue(yield Promise\timeout($dropped->promise(), self::TIMEOUT));
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
            $appearedCounter = 0;
            $maxAppeared = 20;
            $lastAppeared = new Deferred();
            $dropped = new Deferred();

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
                new class($events, $appearedCounter, $maxAppeared, $lastAppeared) implements EventAppearedOnCatchupSubscription {
                    /** @var array */
                    private $events;
                    /** @var int */
                    private $appearedCounter;
                    /** @var Deferred */
                    private $maxAppeared;
                    /** @var Deferred */
                    private $lastAppeared;

                    public function __construct(array &$events, int &$appearedCounter, int $maxAppeared, Deferred $lastAppeared)
                    {
                        $this->events = &$events;
                        $this->appearedCounter = &$appearedCounter;
                        $this->maxAppeared = $maxAppeared;
                        $this->lastAppeared = $lastAppeared;
                    }

                    public function __invoke(
                        EventStoreCatchUpSubscription $subscription,
                        ResolvedEvent $resolvedEvent
                    ): Promise {
                        if (! SystemStreams::isSystemStream($resolvedEvent->originalEvent()->eventStreamId())) {
                            $this->events[] = $resolvedEvent;
                            ++$this->appearedCounter;

                            if ($this->appearedCounter === $this->maxAppeared) {
                                $this->lastAppeared->resolve(true);
                            }
                        }

                        return new Success();
                    }
                },
                null,
                new class($dropped) implements CatchUpSubscriptionDropped {
                    /** @var Deferred */
                    private $dropped;

                    public function __construct(Deferred $dropped)
                    {
                        $this->dropped = $dropped;
                    }

                    public function __invoke(
                        EventStoreCatchUpSubscription $subscription,
                        SubscriptionDropReason $reason,
                        ?Throwable $exception = null
                    ): void {
                        $this->dropped->resolve(true);
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

            try {
                yield Promise\timeout($lastAppeared->promise(), self::TIMEOUT);
            } catch (TimeoutException $e) {
                $this->fail('Could not wait for all events.');

                return;
            }

            $this->assertCount(20, $events);

            for ($i = 0; $i < 20; $i++) {
                $this->assertSame('et-' . $i, $events[$i]->originalEvent()->eventType());
            }

            $wasDropped = true;

            try {
                yield Promise\timeout($dropped->promise(), 0);
            } catch (TimeoutException $e) {
                $wasDropped = false;
            }

            $this->assertFalse($wasDropped);

            $subscription->stopWithTimeout(self::TIMEOUT);

            $this->assertTrue(yield Promise\timeout($dropped->promise(), 0));
        });
    }

    // @todo: 2 tests missing
}
