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

use Amp\PHPUnit\AsyncTestCase;
use Prooph\EventStore\Common\SystemEventTypes;
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\EventData;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Position;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamAcl;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\UserCredentials;
use ProophTest\EventStoreClient\Helper\EventDataComparer;
use ProophTest\EventStoreClient\Helper\TestEvent;

class read_all_events_forward_with_hard_deleted_stream_should extends AsyncTestCase
{
    use SpecificationWithConnection;

    /** @var EventData[] */
    private array $testEvents;

    private string $streamName;

    private Position $from;

    protected function when(): void
    {
        $this->streamName = 'read_all_events_forward_with_hard_deleted_stream_should' . $this->getName();

        $this->connection->setStreamMetadata(
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

        $result = $this->connection->readAllEventsBackward(Position::end(), 1, false);

        $this->from = $result->nextPosition();

        $this->testEvents = TestEvent::newAmount(20);

        $this->connection->appendToStream(
            $this->streamName,
            ExpectedVersion::NoStream,
            $this->testEvents
        );

        $this->connection->deleteStream(
            $this->streamName,
            ExpectedVersion::Any,
            true
        );
    }

    /** @test */
    public function ensure_deleted_stream(): void
    {
        $this->execute(function (): void {
            $res = $this->connection->readStreamEventsForward($this->streamName, 0, 100, false);
            $this->assertSame(SliceReadStatus::StreamDeleted, $res->status());
            $this->assertCount(0, $res->events());
        });
    }

    /** @test */
    public function returns_all_events_including_tombstone(): void
    {
        $this->execute(function (): void {
            $read = $this->connection->readAllEventsForward($this->from, \count($this->testEvents) + 10, false);

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
            $this->assertSame(SystemEventTypes::StreamDeleted->value, $lastEvent->eventType());
        });
    }
}
