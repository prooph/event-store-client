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
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\CatchUpSubscriptionDropped;
use Prooph\EventStoreClient\CatchUpSubscriptionSettings;
use Prooph\EventStoreClient\EventAppearedOnCatchupSubscription;
use Prooph\EventStoreClient\EventAppearedOnSubscription;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\EventStoreAsyncConnection;
use Prooph\EventStoreClient\EventStoreSubscription;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\Internal\EventStoreCatchUpSubscription;
use Prooph\EventStoreClient\Internal\EventStoreStreamCatchUpSubscription;
use Prooph\EventStoreClient\Internal\ManualResetEventSlim;
use Prooph\EventStoreClient\Internal\ResolvedEvent;
use Prooph\EventStoreClient\SubscriptionDropReason;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;
use function Amp\call;

class subscribe_to_stream_catching_up_should extends TestCase
{
    private const TIMEOUT = 5000;

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

            yield from $function();

            $this->conn->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function be_able_to_subscribe_to_non_existing_stream(): void
    {
        $this->execute(function () {
            $stream = 'be_able_to_subscribe_to_non_existing_stream';

            $appeared = new ManualResetEventSlim(false);
            $dropped = new CountdownEvent(1);

            /** @var EventStoreStreamCatchUpSubscription $subscription */
            $subscription = yield $this->conn->subscribeToStreamFromAsync(
                $stream,
                null,
                CatchUpSubscriptionSettings::default(),
                $this->appearedWithResetEvent($appeared),
                null,
                $this->droppedWithCountdown($dropped)
            );

            yield new Delayed(self::TIMEOUT); // give time for first pull phase

            yield $this->conn->subscribeToStreamAsync(
                $stream,
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

            yield new Delayed(self::TIMEOUT);

            $this->assertFalse(yield $appeared->wait(0), 'Some event appeared');
            $this->assertFalse(yield $dropped->wait(0), 'Subscription was dropped prematurely');

            yield $subscription->stop(self::TIMEOUT);

            $this->assertTrue(yield $dropped->wait(0));
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function be_able_to_subscribe_to_non_existing_stream_and_then_catch_event(): void
    {
        $this->execute(function () {
            $stream = 'be_able_to_subscribe_to_non_existing_stream_and_then_catch_event';

            $appeared = new CountdownEvent(1);
            $dropped = new CountdownEvent(1);

            /** @var EventStoreStreamCatchUpSubscription $subscription */
            $subscription = yield $this->conn->subscribeToStreamFromAsync(
                $stream,
                null,
                CatchUpSubscriptionSettings::default(),
                $this->appearedWithCountdown($appeared),
                null,
                $this->droppedWithCountdown($dropped)
            );

            yield $this->conn->appendToStreamAsync(
                $stream,
                ExpectedVersion::EMPTY_STREAM,
                [TestEvent::newTestEvent()]
            );

            if (! $appeared->wait(self::TIMEOUT)) {
                $this->assertFalse(yield $dropped->wait(0), 'Subscription was dropped prematurely');
                $this->fail('Appeared countdown event timed out');

                return;
            }

            $this->assertFalse(yield $dropped->wait(0));
            yield $subscription->stop(self::TIMEOUT);
            $this->assertTrue(yield $dropped->wait(self::TIMEOUT));
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function allow_multiple_subscriptions_to_same_stream(): void
    {
        $this->execute(function () {
            $stream = 'allow_multiple_subscriptions_to_same_stream';

            $appeared = new CountdownEvent(2);
            $dropped1 = new ManualResetEventSlim(false);
            $dropped2 = new ManualResetEventSlim(false);

            /** @var EventStoreStreamCatchUpSubscription $sub1 */
            $sub1 = yield $this->conn->subscribeToStreamFromAsync(
                $stream,
                null,
                CatchUpSubscriptionSettings::default(),
                $this->appearedWithCountdown($appeared),
                null,
                $this->droppedWithResetEvent($dropped1)
            );

            /** @var EventStoreStreamCatchUpSubscription $sub2 */
            $sub2 = yield $this->conn->subscribeToStreamFromAsync(
                $stream,
                null,
                CatchUpSubscriptionSettings::default(),
                $this->appearedWithCountdown($appeared),
                null,
                $this->droppedWithResetEvent($dropped2)
            );

            yield $this->conn->appendToStreamAsync(
                $stream,
                ExpectedVersion::EMPTY_STREAM,
                [TestEvent::newTestEvent()]
            );

            if (! yield $appeared->wait(self::TIMEOUT)) {
                $this->assertFalse(yield $dropped1->wait(0), 'Subscription1 was dropped prematurely');
                $this->assertFalse(yield $dropped2->wait(0), 'Subscription2 was dropped prematurely');
                $this->fail('Could not wait for all events');

                return;
            }

            $this->assertFalse(yield $dropped1->wait(0));
            yield $sub1->stop(self::TIMEOUT);
            $this->assertTrue(yield $dropped1->wait(self::TIMEOUT));

            $this->assertFalse(yield $dropped2->wait(0));
            yield $sub2->stop(self::TIMEOUT);
            $this->assertTrue(yield $dropped2->wait(self::TIMEOUT));
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function call_dropped_callback_after_stop_method_call(): void
    {
        $this->execute(function () {
            $stream = 'call_dropped_callback_after_stop_method_call';

            $dropped = new CountdownEvent(1);

            /** @var EventStoreStreamCatchUpSubscription $subscription */
            $subscription = yield $this->conn->subscribeToStreamFromAsync(
                $stream,
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
                $this->droppedWithCountdown($dropped)
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
            $stream = 'call_dropped_callback_when_an_error_occurs_while_processing_an_event';

            yield $this->conn->appendToStreamAsync(
                $stream,
                ExpectedVersion::ANY,
                [new EventData(null, 'event', false)]
            );

            $dropped = new CountdownEvent(1);

            yield $this->conn->subscribeToStreamFromAsync(
                $stream,
                null,
                CatchUpSubscriptionSettings::default(),
                new class() implements EventAppearedOnCatchupSubscription {
                    public function __invoke(
                        EventStoreCatchUpSubscription $subscription,
                        ResolvedEvent $resolvedEvent
                    ): Promise {
                        throw new \Exception('Error');
                    }
                },
                null,
                $this->droppedWithCountdown($dropped)
            );

            $this->assertTrue(yield $dropped->wait(self::TIMEOUT));
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function read_all_existing_events_and_keep_listening_to_new_ones(): void
    {
        $this->markTestIncomplete();
    }

    /**
     * @test
     * @throws Throwable
     */
    public function filter_events_and_keep_listening_to_new_ones(): void
    {
        $this->markTestIncomplete();
    }

    /**
     * @test
     * @throws Throwable
     */
    public function filter_events_and_work_if_nothing_was_written_after_subscription(): void
    {
        $this->markTestIncomplete();
    }

    private function appearedWithCountdown(CountdownEvent $appeared): EventAppearedOnCatchupSubscription
    {
        return new class($appeared) implements EventAppearedOnCatchupSubscription {
            /** @var CountdownEvent */
            private $appeared;

            public function __construct(CountdownEvent $appeared)
            {
                $this->appeared = $appeared;
            }

            public function __invoke(
                EventStoreCatchUpSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ): Promise {
                $this->appeared->signal();

                return new Success();
            }
        };
    }

    private function appearedWithResetEvent(ManualResetEventSlim $appeared): EventAppearedOnCatchupSubscription
    {
        return new class($appeared) implements EventAppearedOnCatchupSubscription {
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
                $this->appeared->set();

                return new Success();
            }
        };
    }

    private function droppedWithCountdown(CountdownEvent $dropped): CatchUpSubscriptionDropped
    {
        return new class($dropped) implements CatchUpSubscriptionDropped {
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
        };
    }

    private function droppedWithResetEvent(ManualResetEventSlim $dropped): CatchUpSubscriptionDropped
    {
        return new class($dropped) implements CatchUpSubscriptionDropped {
            /** @var ManualResetEventSlim */
            private $dropped;

            public function __construct(ManualResetEventSlim $dropped)
            {
                $this->dropped = $dropped;
            }

            public function __invoke(
                EventStoreCatchUpSubscription $subscription,
                SubscriptionDropReason $reason,
                ?Throwable $exception = null
            ): void {
                $this->dropped->set();
            }
        };
    }
}
