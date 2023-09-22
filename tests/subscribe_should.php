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
use Amp\TimeoutCancellation;
use Closure;
use Prooph\EventStore\EventStoreSubscription;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SubscriptionDropReason;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;

class subscribe_should extends EventStoreConnectionTestCase
{
    private const Timeout = 10;

    /** @test */
    public function be_able_to_subscribe_to_non_existing_stream_and_then_catch_new_event(): void
    {
        $stream = 'subscribe_should_be_able_to_subscribe_to_non_existing_stream_and_then_catch_created_event';

        $appeared = new DeferredFuture();

        $this->connection->subscribeToStream(
            $stream,
            false,
            $this->eventAppearedResolver($appeared)
        );

        $this->connection->appendToStream($stream, ExpectedVersion::NoStream, [TestEvent::newTestEvent()]);

        try {
            $result = $appeared->getFuture()->await(new TimeoutCancellation(self::Timeout));
            $this->assertTrue($result);
        } catch (CancelledException $e) {
            $this->fail('Appeared countdown event timed out');
        }
    }

    /** @test */
    public function allow_multiple_subscriptions_to_same_stream(): void
    {
        $stream = 'subscribe_should_allow_multiple_subscriptions_to_same_stream';

        $appeared1 = new DeferredFuture();
        $appeared2 = new DeferredFuture();

        $this->connection->subscribeToStream(
            $stream,
            false,
            $this->eventAppearedResolver($appeared1)
        );

        $this->connection->subscribeToStream(
            $stream,
            false,
            $this->eventAppearedResolver($appeared2)
        );

        $this->connection->appendToStream($stream, ExpectedVersion::NoStream, [TestEvent::newTestEvent()]);

        try {
            $result = $appeared1->getFuture()->await(new TimeoutCancellation(self::Timeout));
            $this->assertTrue($result);
        } catch (CancelledException $e) {
            $this->fail('Appeared1 countdown event timed out');
        }

        try {
            $result = $appeared2->getFuture()->await(new TimeoutCancellation(self::Timeout));
            $this->assertTrue($result);
        } catch (CancelledException $e) {
            $this->fail('Appeared2 countdown event timed out');
        }
    }

    /** @test */
    public function call_dropped_callback_after_unsubscribe_method_call(): void
    {
        $stream = 'subscribe_should_call_dropped_callback_after_unsubscribe_method_call';

        $dropped = new DeferredFuture();

        $subscription = $this->connection->subscribeToStream(
            $stream,
            false,
            function (
                EventStoreSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ): void {
            },
            $this->subscriptionDroppedResolver($dropped)
        );

        $subscription->unsubscribe();

        try {
            $result = $dropped->getFuture()->await(new TimeoutCancellation(self::Timeout));
            $this->assertTrue($result);
        } catch (CancelledException $e) {
            $this->fail('Dropdown countdown event timed out');
        }
    }

    /** @test */
    public function catch_deleted_events_as_well(): void
    {
        $stream = 'subscribe_should_catch_created_and_deleted_events_as_well';

        $appeared = new DeferredFuture();

        $this->connection->subscribeToStream(
            $stream,
            false,
            $this->eventAppearedResolver($appeared)
        );

        $this->connection->deleteStream($stream, ExpectedVersion::NoStream, true);

        try {
            $result = $appeared->getFuture()->await(new TimeoutCancellation(self::Timeout));
            $this->assertTrue($result);
        } catch (CancelledException $e) {
            $this->fail('Appeared countdown event timed out');
        }
    }

    private function eventAppearedResolver(DeferredFuture $deferred): Closure
    {
        return function (
            EventStoreSubscription $subscription,
            ResolvedEvent $resolvedEvent
        ) use ($deferred): void {
            $deferred->complete(true);
        };
    }

    private function subscriptionDroppedResolver(DeferredFuture $deferred): Closure
    {
        return function (
            EventStoreSubscription $subscription,
            SubscriptionDropReason $reason,
            ?Throwable $exception = null
        ) use ($deferred): void {
            $deferred->complete(true);
        };
    }
}
