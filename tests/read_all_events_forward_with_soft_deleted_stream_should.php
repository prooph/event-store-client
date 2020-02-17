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

use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Common\SystemEventTypes;
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\EventData;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\Util\Json;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;

class read_all_events_forward_with_soft_deleted_stream_should extends TestCase
{
    use SpecificationWithConnection;

    /** @var EventData[] */
    private array $testEvents;
    private string $streamName = 'read_all_events_forward_with_soft_deleted_stream_should';

    private function cleanUp(): Generator
    {
        yield $this->conn->setStreamMetadataAsync(
            '$all',
            ExpectedVersion::ANY,
            StreamMetadata::create()->build(),
            DefaultData::adminCredentials()
        );

        $this->conn->close();
    }

    protected function when(): Generator
    {
        yield $this->conn->setStreamMetadataAsync(
            '$all',
            ExpectedVersion::ANY,
            StreamMetadata::create()->setReadRoles(SystemRoles::ALL)->build(),
            DefaultData::adminCredentials()
        );

        $this->testEvents = TestEvent::newAmount(20);

        yield $this->conn->appendToStreamAsync(
            $this->streamName,
            ExpectedVersion::ANY,
            $this->testEvents
        );

        yield $this->conn->deleteStreamAsync(
            $this->streamName,
            ExpectedVersion::ANY
        );
    }

    /**
     * @test
     * @throws Throwable
     */
    public function ensure_deleted_stream(): void
    {
        $this->execute(function () {
            $res = yield $this->conn->readStreamEventsForwardAsync($this->streamName, 0, 100, false);
            \assert($res instanceof StreamEventsSlice);
            $this->assertTrue($res->status()->equals(SliceReadStatus::streamNotFound()));
            $this->assertCount(0, $res->events());
            $this->cleanUp();
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function returns_all_events_including_tombstone(): void
    {
        $this->execute(function () {
            $metadataEvents = yield $this->conn->readStreamEventsBackwardAsync(
                '$$' . $this->streamName,
                -1,
                1,
                true,
                DefaultData::adminCredentials()
            );
            \assert($metadataEvents instanceof StreamEventsSlice);

            $lastEvent = $metadataEvents->events()[0]->event();
            $this->assertSame('$$' . $this->streamName, $lastEvent->eventStreamId());
            $this->assertSame(SystemEventTypes::STREAM_METADATA, $lastEvent->eventType());
            $metadata = StreamMetadata::createFromArray(Json::decode($lastEvent->data()));
            $this->assertSame(EventNumber::DELETED_STREAM, $metadata->truncateBefore());
        });
    }
}
