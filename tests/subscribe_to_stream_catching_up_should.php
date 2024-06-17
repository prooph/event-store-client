<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2024 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2024 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
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
use Closure;
use Prooph\EventStore\CatchUpSubscriptionSettings;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventStoreCatchUpSubscription;
use Prooph\EventStore\EventStoreSubscription;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SubscriptionDropReason;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;

class subscribe_to_stream_catching_up_should extends EventStoreConnectionTestCase
{
    private const Timeout = 5;

    /** @test */
    public function be_able_to_subscribe_to_non_existing_stream(): void
    {
        $stream = 'be_able_to_subscribe_to_non_existing_stream';

        $appeared = new DeferredFuture();
        $dropped = new CountdownEvent(1);

        $subscription = $this->connection->subscribeToStreamFrom(
            $stream,
            null,
            CatchUpSubscriptionSettings::default(),
            $this->appearedWithResetEvent($appeared),
            null,
            $this->droppedWithCountdown($dropped)
        );

        delay(0.1); // give time for first pull phase

        $this->connection->subscribeToStream(
            $stream,
            false,
            function (
                EventStoreSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ): void {
            }
        );

        delay(0.1);

        $this->assertFalse($this->timeoutWithDefault($appeared->getFuture(), 0, false), 'Some event appeared');
        $this->assertFalse($dropped->wait(0), 'Subscription was dropped prematurely');

        $subscription->stop(self::Timeout);

        $this->assertTrue($dropped->wait(0));
    }

    /** @test */
    public function be_able_to_subscribe_to_non_existing_stream_and_then_catch_event(): void
    {
        $stream = 'be_able_to_subscribe_to_non_existing_stream_and_then_catch_event';

        $appeared = new CountdownEvent(1);
        $dropped = new CountdownEvent(1);

        $subscription = $this->connection->subscribeToStreamFrom(
            $stream,
            null,
            CatchUpSubscriptionSettings::default(),
            $this->appearedWithCountdown($appeared),
            null,
            $this->droppedWithCountdown($dropped)
        );

        $this->connection->appendToStream(
            $stream,
            ExpectedVersion::NoStream,
            [TestEvent::newTestEvent()]
        );

        if (! $appeared->wait(self::Timeout)) {
            $this->assertFalse($dropped->wait(0), 'Subscription was dropped prematurely');
            $this->fail('Appeared countdown event timed out');
        }

        $this->assertFalse($dropped->wait(0));
        $subscription->stop(self::Timeout);
        $this->assertTrue($dropped->wait(self::Timeout));
    }

    /** @test */
    public function allow_multiple_subscriptions_to_same_stream(): void
    {
        $stream = 'allow_multiple_subscriptions_to_same_stream';

        $appeared = new CountdownEvent(2);
        $dropped1 = new DeferredFuture();
        $dropped2 = new DeferredFuture();

        $sub1 = $this->connection->subscribeToStreamFrom(
            $stream,
            null,
            CatchUpSubscriptionSettings::default(),
            $this->appearedWithCountdown($appeared),
            null,
            $this->droppedWithResetEvent($dropped1)
        );

        $sub2 = $this->connection->subscribeToStreamFrom(
            $stream,
            null,
            CatchUpSubscriptionSettings::default(),
            $this->appearedWithCountdown($appeared),
            null,
            $this->droppedWithResetEvent($dropped2)
        );

        $this->connection->appendToStream(
            $stream,
            ExpectedVersion::NoStream,
            [TestEvent::newTestEvent()]
        );

        if (! $appeared->wait(self::Timeout)) {
            $this->assertFalse($this->timeoutWithDefault($dropped1->getFuture(), 0, false), 'Subscription1 was dropped prematurely');
            $this->assertFalse($this->timeoutWithDefault($dropped2->getFuture(), 0, false), 'Subscription2 was dropped prematurely');
            $this->fail('Could not wait for all events');
        }

        $this->assertFalse($this->timeoutWithDefault($dropped1->getFuture(), 0, false));
        $sub1->stop(self::Timeout);
        $this->assertTrue($this->timeoutWithDefault($dropped1->getFuture(), self::Timeout, false));

        $this->assertFalse($this->timeoutWithDefault($dropped2->getFuture(), 0, false));
        $sub2->stop(self::Timeout);
        $this->assertTrue($this->timeoutWithDefault($dropped2->getFuture(), self::Timeout, false));
    }

    /** @test */
    public function call_dropped_callback_after_stop_method_call(): void
    {
        $stream = 'call_dropped_callback_after_stop_method_call';

        $dropped = new CountdownEvent(1);

        $subscription = $this->connection->subscribeToStreamFrom(
            $stream,
            null,
            CatchUpSubscriptionSettings::default(),
            function (
                EventStoreCatchUpSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ): void {
            },
            null,
            $this->droppedWithCountdown($dropped)
        );

        $this->assertFalse($dropped->wait(0));
        $subscription->stop(self::Timeout);
        $this->assertTrue($dropped->wait(self::Timeout));
    }

    /** @test */
    public function call_dropped_callback_when_an_error_occurs_while_processing_an_event(): void
    {
        $stream = 'call_dropped_callback_when_an_error_occurs_while_processing_an_event';

        $this->connection->appendToStream(
            $stream,
            ExpectedVersion::Any,
            [new EventData(null, 'event', false)]
        );

        $dropped = new CountdownEvent(1);

        $this->connection->subscribeToStreamFrom(
            $stream,
            null,
            CatchUpSubscriptionSettings::default(),
            function (
                EventStoreCatchUpSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ): void {
                throw new \Exception('Error');
            },
            null,
            $this->droppedWithCountdown($dropped)
        );

        $this->assertTrue($dropped->wait(self::Timeout));
    }

    /** @test */
    public function read_all_existing_events_and_keep_listening_to_new_ones(): void
    {
        $stream = 'read_all_existing_events_and_keep_listening_to_new_ones';

        /** @var ResolvedEvent[] $events */
        $events = [];
        $appeared = new CountdownEvent(20);
        $dropped = new CountdownEvent(1);

        for ($i = 0; $i < 10; $i++) {
            $this->connection->appendToStream(
                $stream,
                $i - 1,
                [new EventData(null, 'et-' . $i, false)]
            );
        }

        $subscription = $this->connection->subscribeToStreamFrom(
            $stream,
            null,
            CatchUpSubscriptionSettings::default(),
            $this->appearedWithCountdownAndEventsAdd($events, $appeared),
            null,
            $this->droppedWithCountdown($dropped)
        );

        for ($i = 10; $i < 20; $i++) {
            $this->connection->appendToStream(
                $stream,
                $i - 1,
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

    /** @test */
    public function filter_events_and_keep_listening_to_new_ones(): void
    {
        $stream = 'filter_events_and_keep_listening_to_new_ones';

        /** @var ResolvedEvent[] $events */
        $events = [];
        $appeared = new CountdownEvent(20); // skip first 10 events
        $dropped = new CountdownEvent(1);

        for ($i = 0; $i < 20; $i++) {
            $this->connection->appendToStream(
                $stream,
                $i - 1,
                [new EventData(null, 'et-' . $i, false)]
            );
        }

        $subscription = $this->connection->subscribeToStreamFrom(
            $stream,
            9,
            CatchUpSubscriptionSettings::default(),
            $this->appearedWithCountdownAndEventsAdd($events, $appeared),
            null,
            $this->droppedWithCountdown($dropped)
        );

        for ($i = 20; $i < 30; $i++) {
            $this->connection->appendToStream(
                $stream,
                $i - 1,
                [new EventData(null, 'et-' . $i, false)]
            );
        }

        if (! $appeared->wait(self::Timeout)) {
            $this->assertFalse($dropped->wait(0), 'Subscription was dropped prematurely');
            $this->fail('Could not wait for all events');
        }

        $this->assertCount(20, $events);

        for ($i = 0; $i < 20; $i++) {
            $this->assertSame('et-' . ($i + 10), $events[$i]->originalEvent()->eventType());
        }

        $this->assertFalse($dropped->wait(0));
        $subscription->stop(self::Timeout);
        $this->assertTrue($dropped->wait(self::Timeout));

        $this->assertSame($events[19]->originalEventNumber(), $subscription->lastProcessedEventNumber());

        $subscription->stop(0);
    }

    /** @test */
    public function filter_events_and_work_if_nothing_was_written_after_subscription(): void
    {
        $stream = 'filter_events_and_work_if_nothing_was_written_after_subscription';

        /** @var ResolvedEvent[] $events */
        $events = [];
        $appeared = new CountdownEvent(10);
        $dropped = new CountdownEvent(1);

        for ($i = 0; $i < 20; $i++) {
            $this->connection->appendToStream(
                $stream,
                $i - 1,
                [new EventData(null, 'et-' . $i, false)]
            );
        }

        $subscription = $this->connection->subscribeToStreamFrom(
            $stream,
            9,
            CatchUpSubscriptionSettings::default(),
            $this->appearedWithCountdownAndEventsAdd($events, $appeared),
            null,
            $this->droppedWithCountdown($dropped)
        );

        if (! $appeared->wait(self::Timeout)) {
            $this->assertFalse($dropped->wait(0), 'Subscription was dropped prematurely');
            $this->fail('Could not wait for all events');
        }

        $this->assertCount(10, $events);

        for ($i = 0; $i < 10; $i++) {
            $this->assertSame('et-' . ($i + 10), $events[$i]->originalEvent()->eventType());
        }

        $this->assertFalse($dropped->wait(0));
        $subscription->stop(self::Timeout);
        $this->assertTrue($dropped->wait(self::Timeout));

        $this->assertSame($events[9]->originalEventNumber(), $subscription->lastProcessedEventNumber());
    }

    private function appearedWithCountdown(CountdownEvent $appeared): Closure
    {
        return function (
            EventStoreCatchUpSubscription $subscription,
            ResolvedEvent $resolvedEvent
        ) use ($appeared): void {
            $appeared->signal();
        };
    }

    private function appearedWithCountdownAndEventsAdd(array &$events, CountdownEvent $appeared): Closure
    {
        return function (
            EventStoreCatchUpSubscription $subscription,
            ResolvedEvent $resolvedEvent
        ) use (&$events, $appeared): void {
            $events[] = $resolvedEvent;
            $appeared->signal();
        };
    }

    private function appearedWithResetEvent(DeferredFuture $appeared): Closure
    {
        return function (
            EventStoreCatchUpSubscription $subscription,
            ResolvedEvent $resolvedEvent
        ) use ($appeared): void {
            $appeared->complete(true);
        };
    }

    private function droppedWithCountdown(CountdownEvent $dropped): Closure
    {
        return function (
            EventStoreCatchUpSubscription $subscription,
            SubscriptionDropReason $reason,
            ?Throwable $exception = null
        ) use ($dropped): void {
            $dropped->signal();
        };
    }

    private function droppedWithResetEvent(DeferredFuture $dropped): Closure
    {
        return function (
            EventStoreCatchUpSubscription $subscription,
            SubscriptionDropReason $reason,
            ?Throwable $exception = null
        ) use ($dropped): void {
            $dropped->complete(true);
        };
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
