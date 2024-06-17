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
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\EventReadStatus;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Exception\OutOfRangeException;
use Prooph\EventStore\Util\Guid;

class read_event_should extends AsyncTestCase
{
    use SpecificationWithConnection;

    private EventId $eventId0;

    private EventId $eventId1;

    private string $testStream;

    private string $deletedStream;

    protected function when(): void
    {
        $this->eventId0 = EventId::generate();
        $this->eventId1 = EventId::generate();
        $this->testStream = 'test-stream-' . Guid::generateAsHex();
        $this->deletedStream = 'deleted-stream' . Guid::generateAsHex();

        $this->connection->appendToStream($this->testStream, -1, [
            new EventData($this->eventId0, 'event0', false, '123', '456'),
            new EventData($this->eventId1, 'event1', true, '{"foo":"bar"}', '{"meta":"data"}'),
        ]);

        $this->connection->deleteStream($this->deletedStream, -1, true);
    }

    /** @test */
    public function throw_if_stream_id_is_empty(): void
    {
        $this->execute(function (): void {
            $this->expectException(InvalidArgumentException::class);
            $this->connection->readEvent('', 0, false);
        });
    }

    /** @test */
    public function throw_if_event_number_is_less_than_minus_one(): void
    {
        $this->execute(function (): void {
            $this->expectException(OutOfRangeException::class);
            $this->connection->readEvent('stream', -2, false);
        });
    }

    /** @test */
    public function notify_using_status_code_if_stream_not_found(): void
    {
        $this->execute(function (): void {
            $res = $this->connection->readEvent('unexisting-stream', 5, false);

            $this->assertSame(EventReadStatus::NoStream, $res->status());
            $this->assertNull($res->event());
            $this->assertSame('unexisting-stream', $res->stream());
            $this->assertSame(5, $res->eventNumber());
        });
    }

    /** @test */
    public function return_no_stream_if_requested_last_event_in_empty_stream(): void
    {
        $this->execute(function (): void {
            $res = $this->connection->readEvent('some-really-empty-stream', -1, false);

            $this->assertSame(EventReadStatus::NoStream, $res->status());
        });
    }

    /** @test */
    public function notify_using_status_code_if_stream_was_deleted(): void
    {
        $this->execute(function (): void {
            $res = $this->connection->readEvent($this->deletedStream, 5, false);

            $this->assertSame(EventReadStatus::StreamDeleted, $res->status());
            $this->assertNull($res->event());
            $this->assertSame($this->deletedStream, $res->stream());
            $this->assertSame(5, $res->eventNumber());
        });
    }

    /** @test */
    public function notify_using_status_code_if_stream_does_not_have_event(): void
    {
        $this->execute(function (): void {
            $res = $this->connection->readEvent($this->testStream, 5, false);

            $this->assertSame(EventReadStatus::NotFound, $res->status());
            $this->assertNull($res->event());
            $this->assertSame($this->testStream, $res->stream());
            $this->assertSame(5, $res->eventNumber());
        });
    }

    /** @test */
    public function return_existing_event(): void
    {
        $this->execute(function (): void {
            $res = $this->connection->readEvent($this->testStream, 0, false);

            $this->assertSame(EventReadStatus::Success, $res->status());
            $this->assertTrue($res->event()->originalEvent()->eventId()->equals($this->eventId0));
            $this->assertSame($this->testStream, $res->stream());
            $this->assertSame(0, $res->eventNumber());
            $this->assertNotEquals(new \DateTimeImmutable(), $res->event()->originalEvent()->created());
        });
    }

    /** @test */
    public function retrieve_the_is_json_flag_properly(): void
    {
        $this->execute(function (): void {
            $res = $this->connection->readEvent($this->testStream, 1, false);

            $this->assertSame(EventReadStatus::Success, $res->status());
            $this->assertTrue($res->event()->originalEvent()->eventId()->equals($this->eventId1));
            $this->assertTrue($res->event()->originalEvent()->isJson());
        });
    }

    /** @test */
    public function return_last_event_in_stream_if_event_number_is_minus_one(): void
    {
        $this->execute(function (): void {
            $res = $this->connection->readEvent($this->testStream, -1, false);

            $this->assertSame(EventReadStatus::Success, $res->status());
            $this->assertTrue($res->event()->originalEvent()->eventId()->equals($this->eventId1));
            $this->assertSame($this->testStream, $res->stream());
            $this->assertSame(-1, $res->eventNumber());
            $this->assertNotEquals(new \DateTimeImmutable(), $res->event()->originalEvent()->created());
        });
    }
}
