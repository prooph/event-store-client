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

use Amp\PHPUnit\AsyncTestCase;
use Generator;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
use ProophTest\EventStoreClient\Helper\EventsStream;
use ProophTest\EventStoreClient\Helper\StreamWriter;
use ProophTest\EventStoreClient\Helper\TailWriter;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;

class appending_to_implicitly_created_stream extends AsyncTestCase
{
    /**
     * @test
     */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0em1_idempotent(): Generator
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0em1_idempotent';

        $connection = TestConnection::create();

        yield $connection->connectAsync();

        $events = TestEvent::newAmount(6);

        $writer = new StreamWriter($connection, $stream, ExpectedVersion::NO_STREAM);

        $tailWriter = yield $writer->append($events);
        \assert($tailWriter instanceof TailWriter);
        $tailWriter->then($events[0], ExpectedVersion::NO_STREAM);

        $total = yield EventsStream::count($connection, $stream);

        $this->assertCount($total, $events);
    }

    /**
     * @test
     */
    public function sequence_0em1_1e0_2e1_3e2_4e3_4e4_0any_idempotent(): Generator
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_0em1_1e0_2e1_3e2_4e3_4e4_0any_idempotent';

        $connection = TestConnection::create();

        yield $connection->connectAsync();

        $events = TestEvent::newAmount(6);

        $writer = new StreamWriter($connection, $stream, ExpectedVersion::NO_STREAM);

        $tailWriter = yield $writer->append($events);
        \assert($tailWriter instanceof TailWriter);
        yield $tailWriter->then($events[0], ExpectedVersion::ANY);

        $total = yield EventsStream::count($connection, $stream);

        $this->assertCount($total, $events);
    }

    /**
     * @test
     */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e5_non_idempotent(): Generator
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e5_non_idempotent';

        $connection = TestConnection::create();

        yield $connection->connectAsync();

        $events = TestEvent::newAmount(6);

        $writer = new StreamWriter($connection, $stream, ExpectedVersion::NO_STREAM);

        $first6 = yield $writer->append($events);
        \assert($first6 instanceof TailWriter);

        $this->expectException(WrongExpectedVersion::class);

        yield $first6->then($events[0], 6);
    }

    /**
     * @test
     */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e4_wev(): Generator
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e4_wev';

        $connection = TestConnection::create();

        yield $connection->connectAsync();

        $events = TestEvent::newAmount(6);

        $writer = new StreamWriter($connection, $stream, ExpectedVersion::NO_STREAM);

        $first6 = yield $writer->append($events);
        \assert($first6 instanceof TailWriter);

        $this->expectException(WrongExpectedVersion::class);

        yield $first6->then($events[0], 4);
    }

    /**
     * @test
     */
    public function sequence_0em1_0e0_non_idempotent(): Generator
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_0em1_0e0_non_idempotent';

        $connection = TestConnection::create();

        yield $connection->connectAsync();

        $events = [TestEvent::newTestEvent()];

        $writer = new StreamWriter($connection, $stream, ExpectedVersion::NO_STREAM);

        $tailWriter = yield $writer->append($events);
        \assert($tailWriter instanceof TailWriter);

        yield $tailWriter->then($events[0], 0);

        $total = yield EventsStream::count($connection, $stream);

        $this->assertCount($total - 1, $events);
    }

    /**
     * @test
     */
    public function sequence_0em1_0any_idempotent(): Generator
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_0em1_0any_idempotent';

        $connection = TestConnection::create();

        yield $connection->connectAsync();

        $events = [TestEvent::newTestEvent()];

        $writer = new StreamWriter($connection, $stream, ExpectedVersion::NO_STREAM);

        $tailWriter = yield $writer->append($events);
        \assert($tailWriter instanceof TailWriter);

        yield $tailWriter->then($events[0], ExpectedVersion::ANY);

        $total = yield EventsStream::count($connection, $stream);

        $this->assertCount($total, $events);
    }

    /**
     * @test
     */
    public function sequence_0em1_0em1_idempotent(): Generator
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_0em1_0em1_idempotent';

        $connection = TestConnection::create();

        yield $connection->connectAsync();

        $events = [TestEvent::newTestEvent()];

        $writer = new StreamWriter($connection, $stream, ExpectedVersion::NO_STREAM);

        $tailWriter = yield $writer->append($events);
        \assert($tailWriter instanceof TailWriter);

        yield $tailWriter->then($events[0], ExpectedVersion::NO_STREAM);

        $total = yield EventsStream::count($connection, $stream);

        $this->assertCount($total, $events);
    }

    /**
     * @test
     */
    public function sequence_0em1_1e0_2e1_1any_1any_idempotent(): Generator
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_0em1_1e0_2e1_1any_1any_idempotent';

        $connection = TestConnection::create();

        yield $connection->connectAsync();

        $events = TestEvent::newAmount(3);

        $writer = new StreamWriter($connection, $stream, ExpectedVersion::NO_STREAM);

        $tailWriter = yield $writer->append($events);
        \assert($tailWriter instanceof TailWriter);

        $tailWriter = yield $tailWriter->then($events[1], ExpectedVersion::ANY);

        yield $tailWriter->then($events[1], ExpectedVersion::ANY);

        $total = yield EventsStream::count($connection, $stream);

        $this->assertCount($total, $events);
    }

    /**
     * @test
     */
    public function sequence_S_0em1_1em1_E_S_0em1_E_idempotent(): Generator
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_S_0em1_1em1_E_S_0em1_E_idempotent';

        $connection = TestConnection::create();

        yield $connection->connectAsync();

        $events = TestEvent::newAmount(2);

        yield $connection->appendToStreamAsync($stream, ExpectedVersion::NO_STREAM, $events);
        yield $connection->appendToStreamAsync($stream, ExpectedVersion::NO_STREAM, [$events[0]]);

        $total = yield EventsStream::count($connection, $stream);

        $this->assertCount($total, $events);
    }

    /**
     * @test
     */
    public function sequence_S_0em1_1em1_E_S_0any_E_idempotent(): Generator
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_S_0em1_1em1_E_S_0any_E_idempotent';

        $connection = TestConnection::create();

        yield $connection->connectAsync();

        $events = TestEvent::newAmount(2);

        yield $connection->appendToStreamAsync($stream, ExpectedVersion::NO_STREAM, $events);
        yield $connection->appendToStreamAsync($stream, ExpectedVersion::ANY, [$events[0]]);

        $total = yield EventsStream::count($connection, $stream);

        $this->assertCount($total, $events);
    }

    /**
     * @test
     */
    public function sequence_S_0em1_1em1_E_S_1e0_E_idempotent(): Generator
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_S_0em1_1em1_E_S_1e0_E_idempotent';

        $connection = TestConnection::create();

        yield $connection->connectAsync();

        $events = TestEvent::newAmount(2);

        yield $connection->appendToStreamAsync($stream, ExpectedVersion::NO_STREAM, $events);
        yield $connection->appendToStreamAsync($stream, 0, [$events[1]]);

        $total = yield EventsStream::count($connection, $stream);

        $this->assertCount($total, $events);
    }

    /**
     * @test
     */
    public function sequence_S_0em1_1em1_E_S_1any_E_idempotent(): Generator
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_S_0em1_1em1_E_S_1any_E_idempotent';

        $connection = TestConnection::create();

        yield $connection->connectAsync();

        $events = TestEvent::newAmount(2);

        yield $connection->appendToStreamAsync($stream, ExpectedVersion::NO_STREAM, $events);
        yield $connection->appendToStreamAsync($stream, ExpectedVersion::ANY, [$events[1]]);

        $total = yield EventsStream::count($connection, $stream);

        $this->assertCount($total, $events);
    }

    /**
     * @test
     */
    public function sequence_S_0em1_1em1_E_S_0em1_1em1_2em1_E_idempotancy_fail(): Generator
    {
        $stream = 'appending_to_implicitly_created_stream_sequence_S_0em1_1em1_E_S_0em1_1em1_2em1_E_idempotancy_fail';

        $connection = TestConnection::create();

        yield $connection->connectAsync();

        $events = TestEvent::newAmount(2);

        yield $connection->appendToStreamAsync($stream, ExpectedVersion::NO_STREAM, $events);

        $events[] = TestEvent::newTestEvent();

        $this->expectException(WrongExpectedVersion::class);

        yield $connection->appendToStreamAsync($stream, ExpectedVersion::NO_STREAM, $events);
    }
}
