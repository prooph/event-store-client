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

use Amp\Loop;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\Exception\WrongExpectedVersionException;
use Prooph\EventStoreClient\ExpectedVersion;
use ProophTest\EventStoreClient\Helper\Connection;
use ProophTest\EventStoreClient\Helper\EventsStream;
use ProophTest\EventStoreClient\Helper\StreamWriter;
use ProophTest\EventStoreClient\Helper\TailWriter;
use ProophTest\EventStoreClient\Helper\TestEvent;

class AppendingToImplicitlyCreatedStreamTest extends TestCase
{
    /** @test */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0em1_idempotent(): void
    {
        Loop::run(function () {
            $stream = 'appending_to_implicitly_created_stream_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0em1_idempotent';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            $events = [TestEvent::new(), TestEvent::new(), TestEvent::new(), TestEvent::new(), TestEvent::new(), TestEvent::new()];

            $writer = new StreamWriter($connection, $stream, ExpectedVersion::NoStream);

            /** @var TailWriter $tailWriter */
            $tailWriter = yield $writer->append($events);
            $tailWriter->then($events[0], ExpectedVersion::NoStream);

            $total = yield EventsStream::count($connection, $stream);

            $this->assertCount($total, $events);

            $connection->close();
        });
    }

    /** @test */
    public function sequence_0em1_1e0_2e1_3e2_4e3_4e4_0any_idempotent(): void
    {
        Loop::run(function () {
            $stream = 'appending_to_implicitly_created_stream_sequence_0em1_1e0_2e1_3e2_4e3_4e4_0any_idempotent';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            $events = [TestEvent::new(), TestEvent::new(), TestEvent::new(), TestEvent::new(), TestEvent::new(), TestEvent::new()];

            $writer = new StreamWriter($connection, $stream, ExpectedVersion::NoStream);

            /** @var TailWriter $tailWriter */
            $tailWriter = yield $writer->append($events);
            $tailWriter->then($events[0], ExpectedVersion::Any);

            $total = yield EventsStream::count($connection, $stream);

            $this->assertCount($total, $events);

            $connection->close();
        });
    }

    /** @test */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e5_non_idempotent(): void
    {
        Loop::run(function () {
            $stream = 'appending_to_implicitly_created_stream_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e5_non_idempotent';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            $events = [TestEvent::new(), TestEvent::new(), TestEvent::new(), TestEvent::new(), TestEvent::new(), TestEvent::new()];

            $writer = new StreamWriter($connection, $stream, ExpectedVersion::NoStream);

            /** @var TailWriter $first6 */
            $first6 = yield $writer->append($events);
            try {
                $this->expectException(WrongExpectedVersionException::class);
                yield $first6->then($events[0], 6);
            } finally {
                $connection->close();
            }
        });
    }

    /** @test */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e4_wev(): void
    {
        Loop::run(function () {
            $stream = 'appending_to_implicitly_created_stream_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e4_wev';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            $events = [TestEvent::new(), TestEvent::new(), TestEvent::new(), TestEvent::new(), TestEvent::new(), TestEvent::new()];

            $writer = new StreamWriter($connection, $stream, ExpectedVersion::NoStream);

            /** @var TailWriter $first6 */
            $first6 = yield $writer->append($events);
            try {
                $this->expectException(WrongExpectedVersionException::class);
                yield $first6->then($events[0], 4);
            } finally {
                $connection->close();
            }
        });
    }

    /** @test */
    public function sequence_0em1_0e0_non_idempotent(): void
    {
        Loop::run(function () {
            $stream = 'appending_to_implicitly_created_stream_sequence_0em1_0e0_non_idempotent';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            $events = [TestEvent::new()];

            $writer = new StreamWriter($connection, $stream, ExpectedVersion::NoStream);

            /** @var TailWriter $tailWriter */
            $tailWriter = yield $writer->append($events);

            yield $tailWriter->then($events[0], 0);

            $total = yield EventsStream::count($connection, $stream);

            $this->assertCount($total - 1, $events);

            $connection->close();
        });
    }

    /** @test */
    public function sequence_0em1_0any_idempotent(): void
    {
        Loop::run(function () {
            $stream = 'appending_to_implicitly_created_stream_sequence_0em1_0any_idempotent';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            $events = [TestEvent::new()];

            $writer = new StreamWriter($connection, $stream, ExpectedVersion::NoStream);

            /** @var TailWriter $tailWriter */
            $tailWriter = yield $writer->append($events);

            yield $tailWriter->then($events[0], ExpectedVersion::Any);

            $total = yield EventsStream::count($connection, $stream);

            $this->assertCount($total, $events);

            $connection->close();
        });
    }
}
