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

use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\AllEventsSlice;
use Prooph\EventStoreClient\Common\SystemEventTypes;
use Prooph\EventStoreClient\Common\SystemRoles;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\Position;
use Prooph\EventStoreClient\SliceReadStatus;
use Prooph\EventStoreClient\StreamAcl;
use Prooph\EventStoreClient\StreamEventsSlice;
use Prooph\EventStoreClient\StreamMetadata;
use Prooph\EventStoreClient\UserCredentials;
use ProophTest\EventStoreClient\Helper\EventDataComparer;
use ProophTest\EventStoreClient\Helper\TestEvent;

class read_all_events_forward_with_hard_deleted_stream_should extends TestCase
{
    use SpecificationWithConnection;

    /** @var EventData[] */
    private $testEvents;
    /** @var string */
    private $streamName;
    /** @var Position */
    private $from;

    protected function when(): Generator
    {
        $this->streamName = 'read_all_events_forward_with_hard_deleted_stream_should' . $this->getName();

        yield $this->conn->setStreamMetadataAsync(
            '$all',
            ExpectedVersion::Any,
            new StreamMetadata(
                null,
                null,
                null,
                null,
                new StreamAcl(
                    [SystemRoles::All]
                )
            ),
            new UserCredentials(SystemUsers::Admin, SystemUsers::DefaultAdminPassword)
        );

        /** @var AllEventsSlice $result */
        $result = yield $this->conn->readAllEventsBackwardAsync(Position::end(), 1, false);

        $this->from = $result->nextPosition();

        $this->testEvents = TestEvent::newAmount(20);

        yield $this->conn->appendToStreamAsync(
            $this->streamName,
            ExpectedVersion::EmptyStream,
            $this->testEvents
        );

        yield $this->conn->deleteStreamAsync(
            $this->streamName,
            ExpectedVersion::Any,
            true
        );
    }

    /**
     * @test
     * @throws \Throwable
     */
    public function ensure_deleted_stream(): void
    {
        $this->executeCallback(function () {
            /** @var StreamEventsSlice $res */
            $res = yield $this->conn->readStreamEventsForwardAsync($this->streamName, 0, 100, false);
            $this->assertTrue($res->status()->equals(SliceReadStatus::streamDeleted()));
            $this->assertCount(0, $res->events());
        });
    }

    /**
     * @test
     * @throws \Throwable
     */
    public function returns_all_events_including_tombstone(): void
    {
        $this->executeCallback(function () {
            /** @var AllEventsSlice $read */
            $read = yield $this->conn->readAllEventsForwardAsync($this->from, \count($this->testEvents) + 10, false);

            $events = [];
            foreach ($read->events() as $event) {
                $events[] = $event->event();
            }

            $this->assertTrue(
                EventDataComparer::allEqual(
                    $this->testEvents,
                    \array_slice($events, \count($events) - \count($this->testEvents) - 1, \count($this->testEvents))
                )
            );

            $lastEvent = \end($events);

            $this->assertSame($this->streamName, $lastEvent->eventStreamId());
            $this->assertSame(SystemEventTypes::StreamDeleted, $lastEvent->eventType());
        });
    }
}
