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
use Prooph\EventStore\AllEventsSlice;
use Prooph\EventStore\Common\SystemEventTypes;
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\EventData;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Position;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamAcl;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\UserCredentials;
use ProophTest\EventStoreClient\Helper\EventDataComparer;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;

class read_all_events_forward_with_hard_deleted_stream_should extends TestCase
{
    use SpecificationWithConnection;

    /** @var EventData[] */
    private array $testEvents;
    private string $streamName;
    private Position $from;

    protected function when(): Generator
    {
        $this->streamName = 'read_all_events_forward_with_hard_deleted_stream_should' . $this->getName();

        yield $this->conn->setStreamMetadataAsync(
            '$all',
            ExpectedVersion::ANY,
            new StreamMetadata(
                null,
                null,
                null,
                null,
                new StreamAcl(
                    [SystemRoles::ALL]
                )
            ),
            new UserCredentials(SystemUsers::ADMIN, SystemUsers::DEFAULT_ADMIN_PASSWORD)
        );

        $result = yield $this->conn->readAllEventsBackwardAsync(Position::end(), 1, false);
        \assert($result instanceof AllEventsSlice);

        $this->from = $result->nextPosition();

        $this->testEvents = TestEvent::newAmount(20);

        yield $this->conn->appendToStreamAsync(
            $this->streamName,
            ExpectedVersion::NO_STREAM,
            $this->testEvents
        );

        yield $this->conn->deleteStreamAsync(
            $this->streamName,
            ExpectedVersion::ANY,
            true
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
            $this->assertTrue($res->status()->equals(SliceReadStatus::streamDeleted()));
            $this->assertCount(0, $res->events());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function returns_all_events_including_tombstone(): void
    {
        $this->execute(function () {
            $read = yield $this->conn->readAllEventsForwardAsync($this->from, \count($this->testEvents) + 10, false);
            \assert($read instanceof AllEventsSlice);

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
            $this->assertSame(SystemEventTypes::STREAM_DELETED, $lastEvent->eventType());
        });
    }
}
