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
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\Internal\UuidGenerator;
use Prooph\EventStoreClient\PersistentSubscriptionSettings;
use function Amp\Promise\timeout;

class happy_case_writing_and_subscribing_to_normal_events_auto_ack extends TestCase
{
    use SpecificationWithConnection;

    /** string */
    private $streamName;
    /** string */
    private $groupName;
    /** @var int */
    private $bufferCount = 10;
    /** @var int */
    private $eventWriteCount = 20;

    /** @var Deferred */
    private $eventsReceived;
    /** @var int */
    private $eventReceivedCount = 0;

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
     * @group by
     */
    public function do_test(): void
    {
        $this->executeCallback(function () {
            $settings = PersistentSubscriptionSettings::default();

            yield $this->conn->createPersistentSubscriptionAsync(
                $this->streamName,
                $this->groupName,
                $settings,
                DefaultData::adminCredentials()
            );

            $this->conn->connectToPersistentSubscription(
                $this->streamName,
                $this->groupName,
                function ($s, $e): Promise {
                    ++$this->eventReceivedCount;
                    if ($this->eventReceivedCount === $this->eventWriteCount) {
                        $this->eventsReceived->resolve(true);
                    }

                    return new Success();
                },
                function ($s, $r, $e) {
                    // subscription dropped
                }
            );

            for ($i = 0; $i < $this->eventWriteCount; $i++) {
                $eventData = new EventData(null, 'SomeEvent', false);

                yield $this->conn->appendToStreamAsync(
                    $this->streamName,
                    ExpectedVersion::Any,
                    [$eventData],
                    DefaultData::adminCredentials()
                );
            }

            try {
                $result = yield timeout($this->eventsReceived->promise(), 10000);
                $this->assertTrue($result);
            } catch (TimeoutException $e) {
                $this->fail('Timed out waiting for events');
            }
        });
    }
}
