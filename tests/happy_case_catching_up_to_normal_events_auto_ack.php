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
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\EventId;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\Internal\UuidGenerator;
use Prooph\EventStoreClient\NamedConsumerStrategy;
use Prooph\EventStoreClient\PersistentSubscriptionSettings;
use Throwable;

class happy_case_catching_up_to_normal_events_auto_ack extends TestCase
{
    use SpecificationWithConnection;

    /** @var string */
    private $streamName; //= Guid.NewGuid().ToString();
    private $groupName; // = Guid.NewGuid().ToString();

    private const BufferCount = 10;
    private const EventWriteCount = self::BufferCount * 2;

    /** @var Deferred */
    private $eventsReceived;
    /** @var int */
    private $eventReceivedCount;

    protected function setUp(): void
    {
        $this->streamName = UuidGenerator::generate();
        $this->groupName = UuidGenerator::generate();
        $this->eventsReceived = new Deferred();
    }

    protected function when(): Generator
    {
        yield new Success();
    }

    /**
     * @test
     * @throws \Throwable
     */
    public function test(): void
    {
        $this->executeCallback(function () {
            $settings = new PersistentSubscriptionSettings(
                true,
                0,
                false,
                2000,
                500,
                10,
                20,
                1000,
                500,
                0,
                30000,
                10,
                NamedConsumerStrategy::roundRobin()
            );

            for ($i = 0; $i < self::EventWriteCount; $i++) {
                $eventData = new EventData(EventId::generate(), 'SomeEvent', false, '', '');

                yield $this->conn->appendToStreamAsync(
                    $this->streamName,
                    ExpectedVersion::Any,
                    [$eventData],
                    DefaultData::adminCredentials()
                );
            }

            yield $this->conn->createPersistentSubscriptionAsync(
                $this->streamName,
                $this->groupName,
                $settings,
                DefaultData::adminCredentials()
            );

            $this->conn->connectToPersistentSubscription(
                $this->streamName,
                $this->groupName,
                function ($sub, $event): Promise {
                    $this->eventsReceived->resolve(true);

                    return new Success();
                },
                null,
                10,
                true,
                DefaultData::adminCredentials()
            );

            try {
                $result = yield Promise\timeout($this->eventsReceived->promise(), 5000);
            } catch (TimeoutException $e) {
                $this->fail('Timed out waiting for events');
            }

            $this->assertTrue($result);
        });
    }
}
