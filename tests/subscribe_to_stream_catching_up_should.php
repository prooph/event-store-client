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

use Amp\Deferred;
use Amp\Delayed;
use Amp\Promise;
use Amp\Success;
use Closure;
use Generator;
use Prooph\EventStore\Async\EventStoreCatchUpSubscription;
use Prooph\EventStore\CatchUpSubscriptionSettings;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventStoreSubscription;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SubscriptionDropReason;
use Prooph\EventStoreClient\Internal\EventStoreStreamCatchUpSubscription;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;

class subscribe_to_stream_catching_up_should extends EventStoreConnectionTestCase
{
    private const TIMEOUT = 5000;

    /** @test */
    public function be_able_to_subscribe_to_non_existing_stream(): Generator
    {
        $stream = 'be_able_to_subscribe_to_non_existing_stream';

        $appeared = new Deferred();
        $dropped = new CountdownEvent(1);

        $subscription = yield $this->connection->subscribeToStreamFromAsync(
            $stream,
            null,
            CatchUpSubscriptionSettings::default(),
            $this->appearedWithResetEvent($appeared),
            null,
            $this->droppedWithCountdown($dropped)
        );
        \assert($subscription instanceof EventStoreStreamCatchUpSubscription);

        yield new Delayed(self::TIMEOUT); // give time for first pull phase

        yield $this->connection->subscribeToStreamAsync(
            $stream,
            false,
            function (
                EventStoreSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ): Promise {
                return new Success();
            }
        );

        yield new Delayed(self::TIMEOUT);

        $this->assertFalse(yield Promise\timeoutWithDefault($appeared->promise(), 0, false), 'Some event appeared');
        $this->assertFalse(yield $dropped->wait(0), 'Subscription was dropped prematurely');

        yield $subscription->stop(self::TIMEOUT);

        $this->assertTrue(yield $dropped->wait(0));
    }

    /** @test */
    public function be_able_to_subscribe_to_non_existing_stream_and_then_catch_event(): Generator
    {
        $stream = 'be_able_to_subscribe_to_non_existing_stream_and_then_catch_event';

        $appeared = new CountdownEvent(1);
        $dropped = new CountdownEvent(1);

        $subscription = yield $this->connection->subscribeToStreamFromAsync(
            $stream,
            null,
            CatchUpSubscriptionSettings::default(),
            $this->appearedWithCountdown($appeared),
            null,
            $this->droppedWithCountdown($dropped)
        );
        \assert($subscription instanceof EventStoreStreamCatchUpSubscription);

        yield $this->connection->appendToStreamAsync(
            $stream,
            ExpectedVersion::NO_STREAM,
            [TestEvent::newTestEvent()]
        );

        if (! yield $appeared->wait(self::TIMEOUT)) {
            $this->assertFalse(yield $dropped->wait(0), 'Subscription was dropped prematurely');
            $this->fail('Appeared countdown event timed out');

            return;
        }

        $this->assertFalse(yield $dropped->wait(0));
        yield $subscription->stop(self::TIMEOUT);
        $this->assertTrue(yield $dropped->wait(self::TIMEOUT));
    }

    /** @test */
    public function allow_multiple_subscriptions_to_same_stream(): Generator
    {
        $stream = 'allow_multiple_subscriptions_to_same_stream';

        $appeared = new CountdownEvent(2);
        $dropped1 = new Deferred();
        $dropped2 = new Deferred();

        $sub1 = yield $this->connection->subscribeToStreamFromAsync(
            $stream,
            null,
            CatchUpSubscriptionSettings::default(),
            $this->appearedWithCountdown($appeared),
            null,
            $this->droppedWithResetEvent($dropped1)
        );
        \assert($sub1 instanceof EventStoreStreamCatchUpSubscription);

        $sub2 = yield $this->connection->subscribeToStreamFromAsync(
            $stream,
            null,
            CatchUpSubscriptionSettings::default(),
            $this->appearedWithCountdown($appeared),
            null,
            $this->droppedWithResetEvent($dropped2)
        );
        \assert($sub2 instanceof EventStoreStreamCatchUpSubscription);

        yield $this->connection->appendToStreamAsync(
            $stream,
            ExpectedVersion::NO_STREAM,
            [TestEvent::newTestEvent()]
        );

        if (! yield $appeared->wait(self::TIMEOUT)) {
            $this->assertFalse(yield Promise\timeoutWithDefault($dropped1->promise(), 0, false), 'Subscription1 was dropped prematurely');
            $this->assertFalse(yield Promise\timeoutWithDefault($dropped2->promise(), 0, false), 'Subscription2 was dropped prematurely');
            $this->fail('Could not wait for all events');

            return;
        }

        $this->assertFalse(yield Promise\timeoutWithDefault($dropped1->promise(), 0, false));
        yield $sub1->stop(self::TIMEOUT);
        $this->assertTrue(yield Promise\timeoutWithDefault($dropped1->promise(), self::TIMEOUT, false));

        $this->assertFalse(yield Promise\timeoutWithDefault($dropped2->promise(), 0, false));
        yield $sub2->stop(self::TIMEOUT);
        $this->assertTrue(yield Promise\timeoutWithDefault($dropped2->promise(), self::TIMEOUT, false));
    }

    /** @test */
    public function call_dropped_callback_after_stop_method_call(): Generator
    {
        $stream = 'call_dropped_callback_after_stop_method_call';

        $dropped = new CountdownEvent(1);

        $subscription = yield $this->connection->subscribeToStreamFromAsync(
            $stream,
            null,
            CatchUpSubscriptionSettings::default(),
            function (
                EventStoreCatchUpSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ): Promise {
                return new Success();
            },
            null,
            $this->droppedWithCountdown($dropped)
        );
        \assert($subscription instanceof EventStoreStreamCatchUpSubscription);

        $this->assertFalse(yield $dropped->wait(0));
        yield $subscription->stop(self::TIMEOUT);
        $this->assertTrue(yield $dropped->wait(self::TIMEOUT));
    }

    /** @test */
    public function call_dropped_callback_when_an_error_occurs_while_processing_an_event(): Generator
    {
        $stream = 'call_dropped_callback_when_an_error_occurs_while_processing_an_event';

        yield $this->connection->appendToStreamAsync(
            $stream,
            ExpectedVersion::ANY,
            [new EventData(null, 'event', false)]
        );

        $dropped = new CountdownEvent(1);

        yield $this->connection->subscribeToStreamFromAsync(
            $stream,
            null,
            CatchUpSubscriptionSettings::default(),
            function (
                EventStoreCatchUpSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ): Promise {
                throw new \Exception('Error');
            },
            null,
            $this->droppedWithCountdown($dropped)
        );

        $this->assertTrue(yield $dropped->wait(self::TIMEOUT));
    }

    /** @test */
    public function read_all_existing_events_and_keep_listening_to_new_ones(): Generator
    {
        $stream = 'read_all_existing_events_and_keep_listening_to_new_ones';

        /** @var ResolvedEvent[] $events */
        $events = [];
        $appeared = new CountdownEvent(20);
        $dropped = new CountdownEvent(1);

        for ($i = 0; $i < 10; $i++) {
            yield $this->connection->appendToStreamAsync(
                $stream,
                $i - 1,
                [new EventData(null, 'et-' . $i, false)]
            );
        }

        $subscription = yield $this->connection->subscribeToStreamFromAsync(
            $stream,
            null,
            CatchUpSubscriptionSettings::default(),
            $this->appearedWithCountdownAndEventsAdd($events, $appeared),
            null,
            $this->droppedWithCountdown($dropped)
        );
        \assert($subscription instanceof EventStoreStreamCatchUpSubscription);

        for ($i = 10; $i < 20; $i++) {
            yield $this->connection->appendToStreamAsync(
                $stream,
                $i - 1,
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
    }

    /** @test */
    public function filter_events_and_keep_listening_to_new_ones(): Generator
    {
        $stream = 'filter_events_and_keep_listening_to_new_ones';

        /** @var ResolvedEvent[] $events */
        $events = [];
        $appeared = new CountdownEvent(20); // skip first 10 events
        $dropped = new CountdownEvent(1);

        for ($i = 0; $i < 20; $i++) {
            yield $this->connection->appendToStreamAsync(
                $stream,
                $i - 1,
                [new EventData(null, 'et-' . $i, false)]
            );
        }

        $subscription = yield $this->connection->subscribeToStreamFromAsync(
            $stream,
            9,
            CatchUpSubscriptionSettings::default(),
            $this->appearedWithCountdownAndEventsAdd($events, $appeared),
            null,
            $this->droppedWithCountdown($dropped)
        );
        \assert($subscription instanceof EventStoreStreamCatchUpSubscription);

        for ($i = 20; $i < 30; $i++) {
            yield $this->connection->appendToStreamAsync(
                $stream,
                $i - 1,
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
            $this->assertSame('et-' . ($i + 10), $events[$i]->originalEvent()->eventType());
        }

        $this->assertFalse(yield $dropped->wait(0));
        yield $subscription->stop(self::TIMEOUT);
        $this->assertTrue(yield $dropped->wait(self::TIMEOUT));

        $this->assertSame($events[19]->originalEventNumber(), $subscription->lastProcessedEventNumber());

        yield $subscription->stop(0);
    }

    /** @test */
    public function filter_events_and_work_if_nothing_was_written_after_subscription(): Generator
    {
        $stream = 'filter_events_and_work_if_nothing_was_written_after_subscription';

        /** @var ResolvedEvent[] $events */
        $events = [];
        $appeared = new CountdownEvent(10);
        $dropped = new CountdownEvent(1);

        for ($i = 0; $i < 20; $i++) {
            yield $this->connection->appendToStreamAsync(
                $stream,
                $i - 1,
                [new EventData(null, 'et-' . $i, false)]
            );
        }

        $subscription = yield $this->connection->subscribeToStreamFromAsync(
            $stream,
            9,
            CatchUpSubscriptionSettings::default(),
            $this->appearedWithCountdownAndEventsAdd($events, $appeared),
            null,
            $this->droppedWithCountdown($dropped)
        );
        \assert($subscription instanceof EventStoreStreamCatchUpSubscription);

        if (! yield $appeared->wait(self::TIMEOUT)) {
            $this->assertFalse(yield $dropped->wait(0), 'Subscription was dropped prematurely');
            $this->fail('Could not wait for all events');

            return;
        }

        $this->assertCount(10, $events);

        for ($i = 0; $i < 10; $i++) {
            $this->assertSame('et-' . ($i + 10), $events[$i]->originalEvent()->eventType());
        }

        $this->assertFalse(yield $dropped->wait(0));
        yield $subscription->stop(self::TIMEOUT);
        $this->assertTrue(yield $dropped->wait(self::TIMEOUT));

        $this->assertSame($events[9]->originalEventNumber(), $subscription->lastProcessedEventNumber());
    }

    private function appearedWithCountdown(CountdownEvent $appeared): Closure
    {
        return function (
            EventStoreCatchUpSubscription $subscription,
            ResolvedEvent $resolvedEvent
        ) use ($appeared): Promise {
            $appeared->signal();

            return new Success();
        };
    }

    private function appearedWithCountdownAndEventsAdd(array &$events, CountdownEvent $appeared): Closure
    {
        return function (
            EventStoreCatchUpSubscription $subscription,
            ResolvedEvent $resolvedEvent
        ) use (&$events, $appeared): Promise {
            $events[] = $resolvedEvent;
            $appeared->signal();

            return new Success();
        };
    }

    private function appearedWithResetEvent(Deferred $appeared): Closure
    {
        return function (
            EventStoreCatchUpSubscription $subscription,
            ResolvedEvent $resolvedEvent
        ) use ($appeared): Promise {
            $appeared->resolve(true);

            return new Success();
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

    private function droppedWithResetEvent(Deferred $dropped): Closure
    {
        return function (
            EventStoreCatchUpSubscription $subscription,
            SubscriptionDropReason $reason,
            ?Throwable $exception = null
        ) use ($dropped): void {
            $dropped->resolve();
        };
    }
}
