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
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\EventId;
use Prooph\EventStoreClient\EventStoreAsyncConnection;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\Internal\ResolvedEvent;
use ProophTest\EventStoreClient\Helper\Connection;
use Throwable;
use function Amp\call;
use function Amp\Promise\timeout;
use function Amp\Promise\wait;

/**
 * @group longrunning
 */
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

            //Create 80000 events
            for($i = 0; $i < 80; $i++) {
                yield $this->connection->appendToStreamAsync(
                    $this->streamName,
                    ExpectedVersion::Any,
                    $this->createThousandEvents()
                );
                \fwrite(\STDOUT, 'batch ' . $i . ' of 80 appended' . PHP_EOL);
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
        $this->markTestSkipped('subscription to $all is buggy');

        wait(call(function () {
            yield $this->setUpTestCase();

            $deferred = new Deferred();

            $this->connection->subscribeToAllFrom(
                null,
                $this->settings,
                function($sub, ResolvedEvent$event) {
                    if ($this->streamName === $event->originalStreamName()
                        && $event->originalEventNumber() % 1000 === 0
                    ) {
                        \fwrite(\STDOUT, sprintf(
                            "Processed %d events\n",
                            $event->originalEventNumber()
                        ));
                    }

                    return new Success();
                },
                function () use ($deferred): void {
                    $deferred->resolve(true);
                },
                null,
                DefaultData::adminCredentials()
            );

            try {
                $result = yield timeout($deferred->promise(), 10 * 60 * 1000);
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
                function($sub, ResolvedEvent $event) {
                    if ($event->originalEventNumber() % 1000 === 0) {
                        \fwrite(\STDOUT, sprintf(
                            "Processed %d events\n",
                            $event->originalEventNumber()
                        ));
                    }

                    return new Success();
                },
                function () use ($deferred): void {
                    $deferred->resolve(true);
                },
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
}
