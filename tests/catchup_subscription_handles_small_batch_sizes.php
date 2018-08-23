<?php
/**
 * This file is part of the prooph/event-store-client.
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
use Prooph\EventStoreClient\CatchUpSubscriptionSettings;
use Prooph\EventStoreClient\EventAppearedOnCatchupSubscription;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\EventId;
use Prooph\EventStoreClient\EventStoreAsyncConnection;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\Internal\EventStoreCatchUpSubscription;
use Prooph\EventStoreClient\Internal\ResolvedEvent;
use Prooph\EventStoreClient\LiveProcessingStarted;
use ProophTest\EventStoreClient\Helper\Connection;
use Throwable;
use function Amp\call;
use function Amp\Promise\timeout;
use function Amp\Promise\wait;

class catchup_subscription_handles_small_batch_sizes extends TestCase
{
    /** @var string */
    private $streamName = 'TestStream';
    /** @var CatchUpSubscriptionSettings */
    private $settings;
    /** @var EventStoreAsyncConnection */
    private $connection;

    private function setUpTestCase(): Promise
    {
        return call(function () {
            $this->connection = Connection::createAsync();
            yield $this->connection->connectAsync();

            //Create 10000 events
            for ($i = 0; $i < 10; $i++) {
                yield $this->connection->appendToStreamAsync(
                    $this->streamName,
                    ExpectedVersion::ANY,
                    $this->createThousandEvents()
                );
                \fwrite(\STDOUT, 'batch ' . $i . ' of 10 appended' . PHP_EOL);
            }

            $this->settings = new CatchUpSubscriptionSettings(100, 1, false, true);
        });
    }

    /** @return EventData[] */
    private function createThousandEvents(): array
    {
        $events = [];

        for ($i = 0; $i < 1000; $i++) {
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

            $this->connection->subscribeToAllFrom(
                null,
                $this->settings,
                $this->eventAppearedResolver(),
                $this->liveProcessingStartedResolver($deferred),
                null,
                DefaultData::adminCredentials()
            );

            try {
                // we wait maximum 5 minutes
                $result = yield timeout($deferred->promise(), 5 * 60 * 1000);
            } catch (TimeoutException $e) {
                $this->fail('Timed out waiting for test to complete');
            }

            $this->assertTrue($result);

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
                // we wait maximum 5 minutes
                $result = yield timeout($deferred->promise(), 5 * 60 * 1000);
            } catch (TimeoutException $e) {
                $this->fail('Timed out waiting for test to complete');
            }

            $this->assertTrue($result);

            $this->tearDownTestCase();
        }));
    }

    private function eventAppearedResolver(): EventAppearedOnCatchupSubscription
    {
        return new class() implements EventAppearedOnCatchupSubscription {
            public function __invoke(
                EventStoreCatchUpSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ): Promise {
                if ($resolvedEvent->originalEventNumber() % 1000 === 0) {
                    \fwrite(\STDOUT, \sprintf(
                        "Processed %d events\n",
                        $resolvedEvent->originalEventNumber()
                    ));
                }

                return new Success();
            }
        };
    }

    private function liveProcessingStartedResolver(
        Deferred $deferred
    ): LiveProcessingStarted {
        return new class($deferred) implements LiveProcessingStarted {
            private $deferred;

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
