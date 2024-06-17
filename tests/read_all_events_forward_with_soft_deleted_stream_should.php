<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2024 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2024 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\PHPUnit\AsyncTestCase;
use Prooph\EventStore\Common\SystemEventTypes;
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\EventData;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\Util\Json;
use ProophTest\EventStoreClient\Helper\TestEvent;

class read_all_events_forward_with_soft_deleted_stream_should extends AsyncTestCase
{
    use SpecificationWithConnection;

    /** @var EventData[] */
    private array $testEvents;

    private string $streamName = 'read_all_events_forward_with_soft_deleted_stream_should';

    private function cleanUp(): void
    {
        $this->connection->setStreamMetadata(
            '$all',
            ExpectedVersion::Any,
            StreamMetadata::create()->build(),
            DefaultData::adminCredentials()
        );
    }

    protected function when(): void
    {
        $this->connection->setStreamMetadata(
            '$all',
            ExpectedVersion::Any,
            StreamMetadata::create()->setReadRoles(SystemRoles::All)->build(),
            DefaultData::adminCredentials()
        );

        $this->testEvents = TestEvent::newAmount(20);

        $this->connection->appendToStream(
            $this->streamName,
            ExpectedVersion::Any,
            $this->testEvents
        );

        $this->connection->deleteStream(
            $this->streamName,
            ExpectedVersion::Any
        );
    }

    /** @test */
    public function ensure_deleted_stream(): void
    {
        $this->execute(function (): void {
            $res = $this->connection->readStreamEventsForward($this->streamName, 0, 100, false);

            $this->assertSame(SliceReadStatus::StreamNotFound, $res->status());
            $this->assertCount(0, $res->events());
            $this->cleanUp();
        });
    }

    /** @test */
    public function returns_all_events_including_tombstone(): void
    {
        $this->execute(function (): void {
            $metadataEvents = $this->connection->readStreamEventsBackward(
                '$$' . $this->streamName,
                -1,
                1,
                true,
                DefaultData::adminCredentials()
            );

            $lastEvent = $metadataEvents->events()[0]->event();
            $this->assertSame('$$' . $this->streamName, $lastEvent->eventStreamId());
            $this->assertSame(SystemEventTypes::StreamMetadata->value, $lastEvent->eventType());
            $metadata = StreamMetadata::createFromArray(Json::decode($lastEvent->data()));
            $this->assertSame(EventNumber::DeleteStream, $metadata->truncateBefore());
        });
    }
}
