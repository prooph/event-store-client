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

use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\RecordedEvent;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamPosition;
use ProophTest\EventStoreClient\Helper\EventDataComparer;
use ProophTest\EventStoreClient\Helper\TestEvent;

class read_event_stream_forward_should extends EventStoreConnectionTestCase
{
    /** @test */
    public function throw_if_count_le_zero(): void
    {
        $stream = 'read_event_stream_forward_should_throw_if_count_le_zero';

        $this->expectException(InvalidArgumentException::class);

        $this->connection->readStreamEventsForward(
            $stream,
            0,
            0,
            false
        );
    }

    /** @test */
    public function throw_if_start_lt_zero(): void
    {
        $stream = 'read_event_stream_forward_should_throw_if_start_lt_zero';

        $this->expectException(InvalidArgumentException::class);

        $this->connection->readStreamEventsForward(
            $stream,
            -1,
            1,
            false
        );
    }

    /** @test */
    public function notify_using_status_code_if_stream_not_found(): void
    {
        $stream = 'read_event_stream_forward_should_notify_using_status_code_if_stream_not_found';

        $read = $this->connection->readStreamEventsForward(
            $stream,
            StreamPosition::Start->value,
            1,
            false
        );

        $this->assertSame(SliceReadStatus::StreamNotFound, $read->status());
    }

    /** @test */
    public function notify_using_status_code_if_stream_was_deleted(): void
    {
        $stream = 'read_event_stream_forward_should_notify_using_status_code_if_stream_was_deleted';

        $this->connection->deleteStream($stream, ExpectedVersion::NoStream, true);

        $read = $this->connection->readStreamEventsForward(
            $stream,
            StreamPosition::Start->value,
            1,
            false
        );

        $this->assertSame(SliceReadStatus::StreamDeleted, $read->status());
    }

    /** @test */
    public function return_no_events_when_called_on_empty_stream(): void
    {
        $stream = 'read_event_stream_forward_should_return_single_event_when_called_on_empty_stream';

        $read = $this->connection->readStreamEventsForward(
            $stream,
            StreamPosition::Start->value,
            1,
            false
        );

        $this->assertCount(0, $read->events());
    }

    /** @test */
    public function return_empty_slice_when_called_on_non_existing_range(): void
    {
        $stream = 'read_event_stream_forward_should_return_empty_slice_when_called_on_non_existing_range';

        $testEvents = TestEvent::newAmount(10);
        $this->connection->appendToStream(
            $stream,
            ExpectedVersion::NoStream,
            $testEvents
        );

        $read = $this->connection->readStreamEventsForward(
            $stream,
            11,
            5,
            false
        );

        $this->assertCount(0, $read->events());
    }

    /** @test */
    public function return_partial_slice_if_no_enough_events_in_stream(): void
    {
        $stream = 'read_event_stream_forward_should_return_partial_slice_if_no_enough_events_in_stream';

        $testEvents = TestEvent::newAmount(10);
        $this->connection->appendToStream(
            $stream,
            ExpectedVersion::NoStream,
            $testEvents
        );

        $read = $this->connection->readStreamEventsForward(
            $stream,
            9,
            5,
            false
        );

        $this->assertCount(1, $read->events());
    }

    /** @test */
    public function throw_when_got_int_max_value_as_max_count(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->connection->readStreamEventsForward(
            'foo',
            StreamPosition::Start->value,
            \PHP_INT_MAX,
            false
        );
    }

    /** @test */
    public function return_events_in_same_order_as_written(): void
    {
        $stream = 'read_event_stream_forward_should_return_events_in_same_order_as_written';

        $testEvents = TestEvent::newAmount(10);
        $this->connection->appendToStream(
            $stream,
            ExpectedVersion::NoStream,
            $testEvents
        );

        $read = $this->connection->readStreamEventsForward(
            $stream,
            StreamPosition::Start->value,
            10,
            false
        );

        $events = \array_map(
            fn (ResolvedEvent $e): RecordedEvent => $e->event(),
            $read->events()
        );

        $this->assertTrue(EventDataComparer::allEqual($testEvents, $events));
    }

    /** @test */
    public function be_able_to_read_slice_from_arbitrary_position(): void
    {
        $stream = 'read_event_stream_forward_should_be_able_to_read_slice_from_arbitrary_position';

        $testEvents = TestEvent::newAmount(10);
        $this->connection->appendToStream(
            $stream,
            ExpectedVersion::NoStream,
            $testEvents
        );

        $read = $this->connection->readStreamEventsForward(
            $stream,
            5,
            2,
            false
        );

        $events = \array_map(
            fn (ResolvedEvent $e): RecordedEvent => $e->event(),
            $read->events()
        );

        $this->assertTrue(EventDataComparer::allEqual(
            \array_slice($testEvents, 5, 2),
            $events
        ));
    }
}
