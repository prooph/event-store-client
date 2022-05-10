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
use Amp\PHPUnit\AsyncTestCase;
use Amp\TimeoutCancellation;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventStorePersistentSubscription;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\Util\Guid;

class happy_case_writing_and_subscribing_to_normal_events_auto_ack extends AsyncTestCase
{
    use SpecificationWithConnection;

    private string $streamName;

    private string $groupName;

    private const BufferCount = 10;

    private const EventWriteCount = self::BufferCount * 2;

    private int $bufferCount = 10;

    private DeferredFuture $eventsReceived;

    private int $eventReceivedCount = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->streamName = Guid::generateAsHex();
        $this->groupName = Guid::generateAsHex();
        $this->eventsReceived = new DeferredFuture();
    }

    /** @test */
    public function test(): void
    {
        $this->execute(function (): void {
            $settings = PersistentSubscriptionSettings::default();

            $this->connection->createPersistentSubscription(
                $this->streamName,
                $this->groupName,
                $settings,
                DefaultData::adminCredentials()
            );

            $this->connection->connectToPersistentSubscription(
                $this->streamName,
                $this->groupName,
                function (
                    EventStorePersistentSubscription $subscription,
                    ResolvedEvent $resolvedEvent,
                    ?int $retryCount = null
                ): void {
                    if (++$this->eventReceivedCount === self::EventWriteCount) {
                        $this->eventsReceived->complete(true);
                    }
                }
            );

            for ($i = 0; $i < self::EventWriteCount; $i++) {
                $eventData = new EventData(null, 'SomeEvent', false);

                $this->connection->appendToStream(
                    $this->streamName,
                    ExpectedVersion::Any,
                    [$eventData],
                    DefaultData::adminCredentials()
                );
            }

            try {
                $result = $this->eventsReceived->getFuture()->await(new TimeoutCancellation(10));
                $this->assertTrue($result);
            } catch (CancelledException $e) {
                $this->fail('Timed out waiting for events');
            }
        });
    }
}
