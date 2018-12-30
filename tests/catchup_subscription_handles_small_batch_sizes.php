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
use Amp\Promise;
use Amp\Success;
use Amp\TimeoutException;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\AsyncEventStoreCatchUpSubscription;
use Prooph\EventStore\AsyncEventStoreConnection;
use Prooph\EventStore\CatchUpSubscriptionSettings;
use Prooph\EventStore\EventAppearedOnAsyncCatchupSubscription;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\LiveProcessingStartedOnAsyncCatchUpSubscription;
use Prooph\EventStore\ResolvedEvent;
use ProophTest\EventStoreClient\Helper\TestConnection;
use Throwable;
use function Amp\call;
use function Amp\Promise\timeout;
use function Amp\Promise\wait;

class catchup_subscription_handles_small_batch_sizes extends TestCase
{
    private const TIMEOUT = 10000;

    /** @var string */
    private $streamName = 'TestStream';
    /** @var CatchUpSubscriptionSettings */
    private $settings;
    /** @var AsyncEventStoreConnection */
    private $connection;

    private function setUpTestCase(): Promise
    {
        return call(function () {
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

    private function tearDownTestCase(): void
    {
        $this->connection->close();
    }

    /**
     * @test
     * @throws Throwable
     */
    public function catchupSubscriptionToAllHandlesManyEventsWithSmallBatchSize(): void
    {
        wait(call(function () {
            yield $this->setUpTestCase();

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

            $this->tearDownTestCase();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function catchupSubscriptionToStreamHandlesManyEventsWithSmallBatchSize(): void
    {
        wait(call(function () {
            yield $this->setUpTestCase();

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

            $this->tearDownTestCase();
        }));
    }

    private function eventAppearedResolver(): EventAppearedOnAsyncCatchupSubscription
    {
        return new class() implements EventAppearedOnAsyncCatchupSubscription {
            public function __invoke(
                AsyncEventStoreCatchUpSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ): Promise {
                return new Success();
            }
        };
    }

    private function liveProcessingStartedResolver(
        Deferred $deferred
    ): LiveProcessingStartedOnAsyncCatchUpSubscription {
        return new class($deferred) implements LiveProcessingStartedOnAsyncCatchUpSubscription {
            private $deferred;

            public function __construct(Deferred $deferred)
            {
                $this->deferred = $deferred;
            }

            public function __invoke(AsyncEventStoreCatchUpSubscription $subscription): void
            {
                $this->deferred->resolve(true);
            }
        };
    }
}
