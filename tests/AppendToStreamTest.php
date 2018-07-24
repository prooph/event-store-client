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
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Exception\StreamDeletedException;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\StreamEventsSlice;
use Prooph\EventStoreClient\WriteResult;
use ProophTest\EventStoreClient\Helper\Connection;
use ProophTest\EventStoreClient\Helper\TestEvent;

class AppendToStreamTest extends TestCase
{
    /** @test */
    public function cannot_append_to_stream_without_name(): void
    {
        Loop::run(function () {
            $connection = Connection::createAsync();
            $this->expectException(InvalidArgumentException::class);
            yield $connection->appendToStreamAsync('', ExpectedVersion::Any, []);
        });
    }

    /** @test */
    public function should_allow_appending_zero_events_to_stream_with_no_problems(): void
    {
        Loop::run(function () {
            $stream1 = 'should_allow_appending_zero_events_to_stream_with_no_problems1';
            $stream2 = 'should_allow_appending_zero_events_to_stream_with_no_problems2';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            /** @var WriteResult $result */
            $result = yield $connection->appendToStreamAsync($stream1, ExpectedVersion::Any, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream1, ExpectedVersion::NoStream, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream1, ExpectedVersion::Any, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream1, ExpectedVersion::NoStream, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            /** @var StreamEventsSlice $slice */
            $slice = yield $connection->readStreamEventsForwardAsync($stream1, 0, 2, false);
            $this->assertCount(0, $slice->events());

            $result = yield $connection->appendToStreamAsync($stream2, ExpectedVersion::NoStream, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream2, ExpectedVersion::Any, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream2, ExpectedVersion::NoStream, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream2, ExpectedVersion::Any, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $slice = yield $connection->readStreamEventsForwardAsync($stream2, 0, 2, false);
            $this->assertCount(0, $slice->events());

            $connection->close();
        });
    }

    /** @test */
    public function should_create_stream_with_no_stream_exp_ver_on_first_write_if_does_not_exist(): void
    {
        Loop::run(function () {
            $stream = 'should_create_stream_with_no_stream_exp_ver_on_first_write_if_does_not_exist';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            /** @var WriteResult $result */
            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::NoStream, [TestEvent::new()]);
            $this->assertSame(0, $result->nextExpectedVersion());

            /** @var StreamEventsSlice $slice */
            $slice = yield $connection->readStreamEventsForwardAsync($stream, 0, 2, false);

            $this->assertCount(1, $slice->events());

            $connection->close();
        });
    }

    /** @test */
    public function should_create_stream_with_any_exp_ver_on_first_write_if_does_not_exist(): void
    {
        Loop::run(function () {
            $stream = 'should_create_stream_with_any_exp_ver_on_first_write_if_does_not_exist';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            /** @var WriteResult $result */
            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::Any, [TestEvent::new()]);
            $this->assertSame(0, $result->nextExpectedVersion());

            /** @var StreamEventsSlice $slice */
            $slice = yield $connection->readStreamEventsForwardAsync($stream, 0, 2, false);
            $this->assertCount(1, $slice->events());

            $connection->close();
        });
    }

    /** @test */
    public function multiple_idempotent_writes(): void
    {
        Loop::run(function () {
            $stream = 'multiple_idempotent_writes';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            $events = [TestEvent::new(), TestEvent::new(), TestEvent::new(), TestEvent::new()];

            /** @var WriteResult $result */
            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::Any, $events);
            $this->assertSame(3, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::Any, $events);
            $this->assertSame(3, $result->nextExpectedVersion());

            $connection->close();
        });
    }

    /** @test */
    public function multiple_idempotent_writes_with_same_id_bug_case(): void
    {
        Loop::run(function () {
            $stream = 'multiple_idempotent_writes_with_same_id_bug_case';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            $x = TestEvent::new();
            $events = [$x, $x, $x, $x, $x, $x];

            /** @var WriteResult $result */
            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::Any, $events);
            $this->assertSame(5, $result->nextExpectedVersion());

            $connection->close();
        });
    }

    /** @test */
    public function in_wtf_multiple_case_of_multiple_writes_expected_version_any_per_all_same_id(): void
    {
        Loop::run(function () {
            $stream = 'in_wtf_multiple_case_of_multiple_writes_expected_version_any_per_all_same_id';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            $x = TestEvent::new();
            $events = [$x, $x, $x, $x, $x, $x];

            /** @var WriteResult $result */
            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::Any, $events);
            $this->assertSame(5, $result->nextExpectedVersion());

            /** @var WriteResult $result */
            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::Any, $events);
            $this->assertSame(0, $result->nextExpectedVersion());

            $connection->close();
        });
    }

    /** @test */
    public function in_slightly_reasonable_multiple_case_of_multiple_writes_with_expected_version_per_all_same_id(): void
    {
        Loop::run(function () {
            $stream = 'in_slightly_reasonable_multiple_case_of_multiple_writes_with_expected_version_per_all_same_id';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            $x = TestEvent::new();
            $events = [$x, $x, $x, $x, $x, $x];

            /** @var WriteResult $result */
            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::EmptyStream, $events);
            $this->assertSame(5, $result->nextExpectedVersion());

            /** @var WriteResult $f */
            $f = yield $connection->appendToStreamAsync($stream, ExpectedVersion::EmptyStream, $events);
            $this->assertSame(5, $f->nextExpectedVersion());

            $connection->close();
        });
    }

    /** @test */
    public function should_fail_writing_with_correct_exp_ver_to_deleted_stream(): void
    {
        Loop::run(function () {
            $stream = 'should_fail_writing_with_correct_exp_ver_to_deleted_stream';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            yield $connection->deleteStreamAsync($stream, ExpectedVersion::EmptyStream, true);

            try {
                $this->expectException(StreamDeletedException::class);
                yield $connection->appendToStreamAsync($stream, ExpectedVersion::NoStream, [TestEvent::new()]);
            } finally {
                $connection->close();
            }
        });
    }

    /** @test */
    public function should_return_log_position_when_writing(): void
    {
        Loop::run(function () {
            $stream = 'should_return_log_position_when_writing';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            /** @var WriteResult $result */
            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::EmptyStream, [TestEvent::new()]);
            $this->assertGreaterThan(0, $result->logPosition()->preparePosition());
            $this->assertGreaterThan(0, $result->logPosition()->commitPosition());

            $connection->close();
        });
    }

    /** @test */
    public function should_fail_writing_with_any_exp_ver_to_deleted_stream(): void
    {
        Loop::run(function () {
            $stream = 'should_fail_writing_with_any_exp_ver_to_deleted_stream';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            try {
                yield $connection->deleteStreamAsync($stream, ExpectedVersion::EmptyStream, true);
            } catch (\Throwable $e) {
                $this->fail($e->getMessage());
            }

            /** @var WriteResult $result */
            try {
                $this->expectException(StreamDeletedException::class);
                yield $connection->appendToStreamAsync($stream, ExpectedVersion::Any, [TestEvent::new()]);
            } finally {
                $connection->close();
            }
        });
    }
}
