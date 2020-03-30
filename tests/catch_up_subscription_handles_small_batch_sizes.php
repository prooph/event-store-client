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
use Amp\Deferred;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use function Amp\Promise\timeout;
use Amp\Success;
use Amp\TimeoutException;
use Generator;
use Prooph\EventStore\Async\EventAppearedOnCatchupSubscription;
use Prooph\EventStore\Async\EventStoreCatchUpSubscription;
use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\Async\LiveProcessingStartedOnCatchUpSubscription;
use Prooph\EventStore\CatchUpSubscriptionSettings;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\ResolvedEvent;
use ProophTest\EventStoreClient\Helper\TestConnection;

class catch_up_subscription_handles_small_batch_sizes extends AsyncTestCase
{
    private const TIMEOUT = 10000;

    private string $streamName = 'TestStream';
    private CatchUpSubscriptionSettings $settings;
    private EventStoreConnection $connection;

    protected function setUpAsync(): Promise
    {
        return call(function (): Generator {
            $this->connection = TestConnection::create();
            yield $this->connection->connectAsync();

            //Create 500 events
            for ($i = 0; $i < 5; $i++) {
                yield $this->connection->appendToStreamAsync(
                    $this->streamName,
                    ExpectedVersion::ANY,
                    $this->createOneHundredEvents()
                );
            }

            $this->settings = new CatchUpSubscriptionSettings(100, 1, false, true);
        });
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

    /**
     * @test
     */
    public function catchupSubscriptionToAllHandlesManyEventsWithSmallBatchSize(): Generator
    {
        $deferred = new Deferred();

        yield $this->connection->subscribeToAllFromAsync(
            null,
            $this->settings,
            $this->eventAppearedResolver(),
            $this->liveProcessingStartedResolver($deferred),
            null,
            DefaultData::adminCredentials()
        );

        try {
            $result = yield timeout($deferred->promise(), self::TIMEOUT);

            $this->assertTrue($result);
        } catch (TimeoutException $e) {
            $this->fail('Timed out waiting for test to complete');
        }
    }

    /**
     * @test
     */
    public function catchupSubscriptionToStreamHandlesManyEventsWithSmallBatchSize(): Generator
    {
        $deferred = new Deferred();

        yield $this->connection->subscribeToStreamFromAsync(
            $this->streamName,
            null,
            $this->settings,
            $this->eventAppearedResolver(),
            $this->liveProcessingStartedResolver($deferred),
            null,
            DefaultData::adminCredentials()
        );

        try {
            $result = yield timeout($deferred->promise(), self::TIMEOUT);

            $this->assertTrue($result);
        } catch (TimeoutException $e) {
            $this->fail('Timed out waiting for test to complete');
        }
    }

    private function eventAppearedResolver(): EventAppearedOnCatchupSubscription
    {
        return new class() implements EventAppearedOnCatchupSubscription {
            public function __invoke(
                EventStoreCatchUpSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ): Promise {
                return new Success();
            }
        };
    }

    private function liveProcessingStartedResolver(
        Deferred $deferred
    ): LiveProcessingStartedOnCatchUpSubscription {
        return new class($deferred) implements LiveProcessingStartedOnCatchUpSubscription {
            private Deferred $deferred;

            public function __construct(Deferred $deferred)
            {
                $this->deferred = $deferred;
            }

            public function __invoke(EventStoreCatchUpSubscription $subscription): void
            {
                $this->deferred->resolve(true);
            }
        };
    }
}
