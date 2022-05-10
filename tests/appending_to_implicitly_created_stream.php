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

use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
use ProophTest\EventStoreClient\Helper\EventsStream;
use ProophTest\EventStoreClient\Helper\StreamWriter;
use ProophTest\EventStoreClient\Helper\TestEvent;

class appending_to_implicitly_created_stream extends EventStoreConnectionTestCase
{
    /** @test */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0em1_idempotent(): void
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0em1_idempotent';

        $events = TestEvent::newAmount(6);

        $writer = new StreamWriter($this->connection, $stream, ExpectedVersion::NoStream);

        $tailWriter = $writer->append($events);
        $tailWriter->then($events[0], ExpectedVersion::NoStream);

        $total = EventsStream::count($this->connection, $stream);

        $this->assertCount($total, $events);
    }

    /** @test */
    public function sequence_0em1_1e0_2e1_3e2_4e3_4e4_0any_idempotent(): void
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_0em1_1e0_2e1_3e2_4e3_4e4_0any_idempotent';

        $events = TestEvent::newAmount(6);

        $writer = new StreamWriter($this->connection, $stream, ExpectedVersion::NoStream);

        $tailWriter = $writer->append($events);
        $tailWriter->then($events[0], ExpectedVersion::Any);

        $total = EventsStream::count($this->connection, $stream);

        $this->assertCount($total, $events);
    }

    /** @test */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e5_non_idempotent(): void
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e5_non_idempotent';

        $events = TestEvent::newAmount(6);

        $writer = new StreamWriter($this->connection, $stream, ExpectedVersion::NoStream);

        $first6 = $writer->append($events);

        $this->expectException(WrongExpectedVersion::class);

        $first6->then($events[0], 6);
    }

    /** @test */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e4_wev(): void
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e4_wev';

        $events = TestEvent::newAmount(6);

        $writer = new StreamWriter($this->connection, $stream, ExpectedVersion::NoStream);

        $first6 = $writer->append($events);

        $this->expectException(WrongExpectedVersion::class);

        $first6->then($events[0], 4);
    }

    /** @test */
    public function sequence_0em1_0e0_non_idempotent(): void
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_0em1_0e0_non_idempotent';

        $events = [TestEvent::newTestEvent()];

        $writer = new StreamWriter($this->connection, $stream, ExpectedVersion::NoStream);

        $tailWriter = $writer->append($events);

        $tailWriter->then($events[0], 0);

        $total = EventsStream::count($this->connection, $stream);

        $this->assertCount($total - 1, $events);
    }

    /** @test */
    public function sequence_0em1_0any_idempotent(): void
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_0em1_0any_idempotent';

        $events = [TestEvent::newTestEvent()];

        $writer = new StreamWriter($this->connection, $stream, ExpectedVersion::NoStream);

        $tailWriter = $writer->append($events);

        $tailWriter->then($events[0], ExpectedVersion::Any);

        $total = EventsStream::count($this->connection, $stream);

        $this->assertCount($total, $events);
    }

    /** @test */
    public function sequence_0em1_0em1_idempotent(): void
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_0em1_0em1_idempotent';

        $events = [TestEvent::newTestEvent()];

        $writer = new StreamWriter($this->connection, $stream, ExpectedVersion::NoStream);

        $tailWriter = $writer->append($events);

        $tailWriter->then($events[0], ExpectedVersion::NoStream);

        $total = EventsStream::count($this->connection, $stream);

        $this->assertCount($total, $events);
    }

    /** @test */
    public function sequence_0em1_1e0_2e1_1any_1any_idempotent(): void
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_0em1_1e0_2e1_1any_1any_idempotent';

        $events = TestEvent::newAmount(3);

        $writer = new StreamWriter($this->connection, $stream, ExpectedVersion::NoStream);

        $tailWriter = $writer->append($events);

        $tailWriter = $tailWriter->then($events[1], ExpectedVersion::Any);

        $tailWriter->then($events[1], ExpectedVersion::Any);

        $total = EventsStream::count($this->connection, $stream);

        $this->assertCount($total, $events);
    }

    /** @test */
    public function sequence_S_0em1_1em1_E_S_0em1_E_idempotent(): void
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_S_0em1_1em1_E_S_0em1_E_idempotent';

        $events = TestEvent::newAmount(2);

        $this->connection->appendToStream($stream, ExpectedVersion::NoStream, $events);
        $this->connection->appendToStream($stream, ExpectedVersion::NoStream, [$events[0]]);

        $total = EventsStream::count($this->connection, $stream);

        $this->assertCount($total, $events);
    }

    /** @test */
    public function sequence_S_0em1_1em1_E_S_0any_E_idempotent(): void
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_S_0em1_1em1_E_S_0any_E_idempotent';

        $events = TestEvent::newAmount(2);

        $this->connection->appendToStream($stream, ExpectedVersion::NoStream, $events);
        $this->connection->appendToStream($stream, ExpectedVersion::Any, [$events[0]]);

        $total = EventsStream::count($this->connection, $stream);

        $this->assertCount($total, $events);
    }

    /** @test */
    public function sequence_S_0em1_1em1_E_S_1e0_E_idempotent(): void
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_S_0em1_1em1_E_S_1e0_E_idempotent';

        $events = TestEvent::newAmount(2);

        $this->connection->appendToStream($stream, ExpectedVersion::NoStream, $events);
        $this->connection->appendToStream($stream, 0, [$events[1]]);

        $total = EventsStream::count($this->connection, $stream);

        $this->assertCount($total, $events);
    }

    /** @test */
    public function sequence_S_0em1_1em1_E_S_1any_E_idempotent(): void
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_S_0em1_1em1_E_S_1any_E_idempotent';

        $events = TestEvent::newAmount(2);

        $this->connection->appendToStream($stream, ExpectedVersion::NoStream, $events);
        $this->connection->appendToStream($stream, ExpectedVersion::Any, [$events[1]]);

        $total = EventsStream::count($this->connection, $stream);

        $this->assertCount($total, $events);
    }

    /** @test */
    public function sequence_S_0em1_1em1_E_S_0em1_1em1_2em1_E_idempotancy_fail(): void
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_S_0em1_1em1_E_S_0em1_1em1_2em1_E_idempotancy_fail';

        $events = TestEvent::newAmount(2);

        $this->connection->appendToStream($stream, ExpectedVersion::NoStream, $events);

        $events[] = TestEvent::newTestEvent();

        $this->expectException(WrongExpectedVersion::class);

        $this->connection->appendToStream($stream, ExpectedVersion::NoStream, $events);
    }
}
