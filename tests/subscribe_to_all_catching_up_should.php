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
use Amp\Future;
use Amp\TimeoutCancellation;
use Exception;
use Prooph\EventStore\CatchUpSubscriptionSettings;
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\Common\SystemStreams;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventStoreCatchUpSubscription;
use Prooph\EventStore\EventStoreSubscription;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Position;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\SubscriptionDropReason;
use Prooph\EventStore\UserCredentials;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;

class subscribe_to_all_catching_up_should extends EventStoreConnectionTestCase
{
    private const Timeout = 10;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection->setStreamMetadata(
            '$all',
            ExpectedVersion::Any,
            StreamMetadata::create()->setReadRoles(SystemRoles::All)->build(),
            new UserCredentials(SystemUsers::Admin, SystemUsers::DefaultAdminPassword)
        );
    }

    protected function tearDown(): void
    {
        $this->connection->setStreamMetadata(
            '$all',
            ExpectedVersion::Any,
            StreamMetadata::create()->build(),
            new UserCredentials(SystemUsers::Admin, SystemUsers::DefaultAdminPassword)
        );

        parent::tearDown();
    }

    /** @test */
    public function call_dropped_callback_after_stop_method_call(): void
    {
        $dropped = new CountdownEvent(1);

        $subscription = $this->connection->subscribeToAllFrom(
            null,
            CatchUpSubscriptionSettings::default(),
            function (
                EventStoreCatchUpSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ): void {
            },
            null,
            function (
                EventStoreCatchUpSubscription $subscription,
                SubscriptionDropReason $reason,
                ?Throwable $exception = null
            ) use ($dropped): void {
                $dropped->signal();
            }
        );

        $this->assertFalse($dropped->wait(0));
        $subscription->stop(self::Timeout);
        $this->assertTrue($dropped->wait(self::Timeout));
    }

    /** @test */
    public function call_dropped_callback_when_an_error_occurs_while_processing_an_event(): void
    {
        $stream = 'all_call_dropped_callback_when_an_error_occurs_while_processing_an_event';

        $this->connection->appendToStream(
            $stream,
            ExpectedVersion::Any,
            [TestEvent::newTestEvent()]
        );

        $dropped = new CountdownEvent(1);

        $subscription = $this->connection->subscribeToAllFrom(
            null,
            CatchUpSubscriptionSettings::default(),
            function (
                EventStoreCatchUpSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ): void {
                throw new Exception('Error');
            },
            null,
            function (
                EventStoreCatchUpSubscription $subscription,
                SubscriptionDropReason $reason,
                ?Throwable $exception = null
            ) use ($dropped): void {
                $dropped->signal();
            }
        );

        $subscription->stop(self::Timeout);

        $this->assertTrue($dropped->wait(self::Timeout));
    }

    /**
     * No way to guarantee an empty db
     *
     * @test
     * @group ignore
     */
    public function be_able_to_subscribe_to_empty_db(): void
    {
        $appeared = new DeferredFuture();
        $dropped = new CountdownEvent(1);

        $subscription = $this->connection->subscribeToAllFrom(
            null,
            CatchUpSubscriptionSettings::default(),
            function (
                EventStoreCatchUpSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ) use ($appeared): void {
                if (! SystemStreams::isSystemStream($resolvedEvent->originalEvent()->eventStreamId())) {
                    $appeared->complete(true);
                }
            },
            null,
            function (
                EventStoreCatchUpSubscription $subscription,
                SubscriptionDropReason $reason,
                ?Throwable $exception = null
            ) use ($dropped): void {
                $dropped->signal();
            }
        );

        delay(5); // give time for first pull phase

        $this->connection->subscribeToAll(
            false,
            function (
                EventStoreSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ): void {
            }
        );

        delay(5);

        $this->assertFalse($this->timeoutWithDefault($appeared->getFuture(), 0, false), 'Some event appeared');
        $this->assertFalse($dropped->wait(0), 'Subscription was dropped prematurely');

        $subscription->stop(self::Timeout);

        $this->assertTrue($dropped->wait(self::Timeout));
    }

    /** @test */
    public function read_all_existing_events_and_keep_listening_to_new_ones(): void
    {
        $result = $this->connection->readAllEventsBackward(Position::end(), 1, false);
        $position = $result->nextPosition();

        $events = [];
        $appeared = new CountdownEvent(20);
        $dropped = new CountdownEvent(1);

        for ($i = 0; $i < 10; $i++) {
            $this->connection->appendToStream(
                'all_read_all_existing_events_and_keep_listening_to_new_ones-' . $i,
                -1,
                [new EventData(null, 'et-' . $i, false)]
            );
        }

        $subscription = $this->connection->subscribeToAllFrom(
            $position,
            CatchUpSubscriptionSettings::default(),
            function (
                EventStoreCatchUpSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ) use (&$events, $appeared): void {
                if (! SystemStreams::isSystemStream($resolvedEvent->originalEvent()->eventStreamId())) {
                    $events[] = $resolvedEvent;
                    $appeared->signal();
                }
            },
            null,
            function (
                EventStoreCatchUpSubscription $subscription,
                SubscriptionDropReason $reason,
                ?Throwable $exception = null
            ) use ($dropped): void {
                $dropped->signal();
            }
        );

        for ($i = 10; $i < 20; $i++) {
            $this->connection->appendToStream(
                'all_read_all_existing_events_and_keep_listening_to_new_ones-' . $i,
                -1,
                [new EventData(null, 'et-' . $i, false)]
            );
        }

        if (! $appeared->wait(self::Timeout)) {
            $this->assertFalse($dropped->wait(0), 'Subscription was dropped prematurely');
            $this->fail('Could not wait for all events');
        }

        $this->assertCount(20, $events);

        for ($i = 0; $i < 20; $i++) {
            $this->assertSame('et-' . $i, $events[$i]->originalEvent()->eventType());
        }

        $this->assertFalse($dropped->wait(0));
        $subscription->stop(self::Timeout);
        $this->assertTrue($dropped->wait(self::Timeout));
    }

    /**
     * Not working against single db
     *
     * @test
     * @group ignore
     */
    public function filter_events_and_keep_listening_to_new_ones(): void
    {
        $events = [];
        $appeared = new CountdownEvent(10);
        $dropped = new CountdownEvent(1);

        for ($i = 0; $i < 10; $i++) {
            $this->connection->appendToStream(
                'all_filter_events_and_keep_listening_to_new_ones-' . $i,
                -1,
                [new EventData(null, 'et-' . $i, false)]
            );
        }

        $allSlice = $this->connection->readAllEventsForward(Position::start(), 100, false);
        $lastEvent = \array_values(\array_slice($allSlice->events(), -1))[0];

        $subscription = $this->connection->subscribeToAllFrom(
            $lastEvent->originalPosition(),
            CatchUpSubscriptionSettings::default(),
            function (
                EventStoreCatchUpSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ) use (&$events, $appeared): void {
                if (! SystemStreams::isSystemStream($resolvedEvent->originalEvent()->eventStreamId())) {
                    $events[] = $resolvedEvent;
                    $appeared->signal();
                }
            },
            null,
            function (
                EventStoreCatchUpSubscription $subscription,
                SubscriptionDropReason $reason,
                ?Throwable $exception = null
            ) use ($dropped): void {
                $dropped->signal();
            }
        );

        for ($i = 10; $i < 20; $i++) {
            $this->connection->appendToStream(
                'all_filter_events_and_keep_listening_to_new_ones-' . $i,
                -1,
                [new EventData(null, 'et-' . $i, false)]
            );
        }

        if (! $appeared->wait(self::Timeout)) {
            $this->assertFalse($dropped->wait(0), 'Subscription was dropped prematurely');
            $this->fail('Could not wait for all events');
        }

        $this->assertCount(10, $events);

        for ($i = 0; $i < 10; $i++) {
            $this->assertSame('et-' . (10 + $i), $events[$i]->originalEvent()->eventType());
        }

        $this->assertFalse($dropped->wait(0));
        $subscription->stop();
        $this->assertTrue($dropped->wait(self::Timeout));

        $lastEvent = \array_values(\array_slice($events, -1))[0];
        $this->assertTrue($lastEvent->originalPosition()->equals($subscription->lastProcessedPosition()));
    }

    /**
     * Not working against single db
     *
     * @test
     * @group ignore
     */
    public function filter_events_and_work_if_nothing_was_written_after_subscription(): void
    {
        /** @var ResolvedEvent[] $events */
        $events = [];
        $appeared = new CountdownEvent(1);
        $dropped = new CountdownEvent(1);

        for ($i = 0; $i < 10; $i++) {
            $this->connection->appendToStream(
                'all_filter_events_and_work_if_nothing_was_written_after_subscription-' . $i,
                -1,
                [new EventData(null, 'et-' . $i, false)]
            );
        }

        $allSlice = $this->connection->readAllEventsBackward(Position::end(), 2, false);
        $lastEvent = $allSlice->events()[1];

        $subscription = $this->connection->subscribeToAllFrom(
            $lastEvent->originalPosition(),
            CatchUpSubscriptionSettings::default(),
            function (
                EventStoreCatchUpSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ) use (&$events, $appeared): void {
                $events[] = $resolvedEvent;
                $appeared->signal();
            },
            null,
            function (
                EventStoreCatchUpSubscription $subscription,
                SubscriptionDropReason $reason,
                ?Throwable $exception = null
            ) use ($dropped): void {
                $dropped->signal();
            }
        );

        if (! $appeared->wait(self::Timeout)) {
            $this->assertFalse($dropped->wait(0), 'Subscription was dropped prematurely');
            $this->fail('Could not wait for all events');
        }

        $this->assertCount(1, $events);
        $this->assertSame('et-9', $events[0]->originalEvent()->eventType());

        $this->assertFalse($dropped->wait(0));
        $subscription->stop(self::Timeout);
        $this->assertTrue($dropped->wait(self::Timeout));

        $this->assertTrue($events[0]->originalPosition()->equals($subscription->lastProcessedPosition()));
    }

    private function timeoutWithDefault(Future $future, int $timeout, mixed $default = null): mixed
    {
        try {
            return $future->await(new TimeoutCancellation($timeout));
        } catch (CancelledException $e) {
            return $default;
        }
    }
}
