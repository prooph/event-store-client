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
use Prooph\EventStore\CatchUpSubscriptionSettings;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\EventStoreCatchUpSubscription;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\ResolvedEvent;

class catch_up_subscription_handles_small_batch_sizes extends EventStoreConnectionTestCase
{
    private const Timeout = 10;

    private string $streamName = 'TestStream';

    private CatchUpSubscriptionSettings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        //Create 500 events
        for ($i = 0; $i < 5; $i++) {
            $this->connection->appendToStream(
                $this->streamName,
                ExpectedVersion::Any,
                $this->createOneHundredEvents()
            );
        }

        $this->settings = new CatchUpSubscriptionSettings(100, 1, false, true);
    }

    /** @return EventData[] */
    private function createOneHundredEvents(): array
    {
        $events = [];

        for ($i = 0; $i < 100; $i++) {
            $events[] = new EventData(EventId::generate(), 'testEvent', true, \json_encode('{ "Foo": "Bar" }'), '');
        }

        return $events;
    }

    /** @test */
    public function catchupSubscriptionToAllHandlesManyEventsWithSmallBatchSize(): void
    {
        $deferred = new DeferredFuture();

        $this->connection->subscribeToAllFrom(
            null,
            $this->settings,
            $this->eventAppearedResolver(),
            $this->liveProcessingStartedResolver($deferred),
            null,
            DefaultData::adminCredentials()
        );

        try {
            $result = $deferred->getFuture()->await(new TimeoutCancellation(self::Timeout));
            $this->assertTrue($result);
        } catch (CancelledException $e) {
            $this->fail('Timed out waiting for test to complete');
        }
    }

    /** @test */
    public function catchupSubscriptionToStreamHandlesManyEventsWithSmallBatchSize(): void
    {
        $deferred = new DeferredFuture();

        $this->connection->subscribeToStreamFrom(
            $this->streamName,
            null,
            $this->settings,
            $this->eventAppearedResolver(),
            $this->liveProcessingStartedResolver($deferred),
            null,
            DefaultData::adminCredentials()
        );

        try {
            $result = $deferred->getFuture()->await(new TimeoutCancellation(self::Timeout));
            $this->assertTrue($result);
        } catch (CancelledException $e) {
            $this->fail('Timed out waiting for test to complete');
        }
    }

    private function eventAppearedResolver(): Closure
    {
        return function (
            EventStoreCatchUpSubscription $subscription,
            ResolvedEvent $resolvedEvent
        ): void {
        };
    }

    private function liveProcessingStartedResolver(
        DeferredFuture $deferred
    ): Closure {
        return function (EventStoreCatchUpSubscription $subscription) use ($deferred): void {
            $deferred->complete(true);
        };
    }
}
