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

use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\Common\SystemEventTypes;
use Prooph\EventStoreClient\Common\SystemRoles;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\Internal\Json;
use Prooph\EventStoreClient\SliceReadStatus;
use Prooph\EventStoreClient\StreamEventsSlice;
use Prooph\EventStoreClient\StreamMetadata;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;

class read_all_events_forward_with_soft_deleted_stream_should extends TestCase
{
    use SpecificationWithConnection;

    /** @var EventData[] */
    private $testEvents;
    /** @var string */
    private $streamName = 'read_all_events_forward_with_soft_deleted_stream_should';

    private function cleanUp(): Generator
    {
        yield $this->conn->setStreamMetadataAsync(
            '$all',
            ExpectedVersion::ANY,
            StreamMetadata::build()->build(),
            DefaultData::adminCredentials()
        );

        $this->conn->close();
    }

    protected function when(): Generator
    {
        yield $this->conn->setStreamMetadataAsync(
            '$all',
            ExpectedVersion::ANY,
            StreamMetadata::build()->setReadRoles(SystemRoles::ALL)->build(),
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
            /** @var StreamEventsSlice $res */
            $res = yield $this->conn->readStreamEventsForwardAsync($this->streamName, 0, 100, false);
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
            /** @var StreamEventsSlice $metadataEvents */
            $metadataEvents = yield $this->conn->readStreamEventsBackwardAsync(
                '$$' . $this->streamName,
                -1,
                1,
                true,
                DefaultData::adminCredentials()
            );

            $lastEvent = $metadataEvents->events()[0]->event();
            $this->assertSame('$$' . $this->streamName, $lastEvent->eventStreamId());
            $this->assertSame(SystemEventTypes::STREAM_METADATA, $lastEvent->eventType());
            $metadata = StreamMetadata::createFromArray(Json::decode($lastEvent->data()));
            $this->assertSame(EventNumber::DELETED_STREAM, $metadata->truncateBefore());
        });
    }
}
