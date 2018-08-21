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

use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\ConditionalWriteResult;
use Prooph\EventStoreClient\ConditionalWriteStatus;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Exception\StreamDeletedException;
use Prooph\EventStoreClient\Exception\WrongExpectedVersionException;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\StreamEventsSlice;
use Prooph\EventStoreClient\StreamMetadata;
use Prooph\EventStoreClient\WriteResult;
use ProophTest\EventStoreClient\Helper\Connection;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;
use function Amp\call;
use function Amp\Promise\wait;

class append_to_stream extends TestCase
{
    /**
     * @test
     * @throws Throwable
     */
    public function cannot_append_to_stream_without_name(): void
    {
        wait(call(function () {
            $connection = Connection::createAsync();
            $this->expectException(InvalidArgumentException::class);
            yield $connection->appendToStreamAsync('', ExpectedVersion::ANY, []);
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_allow_appending_zero_events_to_stream_with_no_problems(): void
    {
        wait(call(function () {
            $stream1 = 'should_allow_appending_zero_events_to_stream_with_no_problems1';
            $stream2 = 'should_allow_appending_zero_events_to_stream_with_no_problems2';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            /** @var WriteResult $result */
            $result = yield $connection->appendToStreamAsync($stream1, ExpectedVersion::ANY, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream1, ExpectedVersion::NO_STREAM, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream1, ExpectedVersion::ANY, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream1, ExpectedVersion::NO_STREAM, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            /** @var StreamEventsSlice $slice */
            $slice = yield $connection->readStreamEventsForwardAsync($stream1, 0, 2, false);
            $this->assertCount(0, $slice->events());

            $result = yield $connection->appendToStreamAsync($stream2, ExpectedVersion::NO_STREAM, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream2, ExpectedVersion::ANY, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream2, ExpectedVersion::NO_STREAM, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream2, ExpectedVersion::ANY, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $slice = yield $connection->readStreamEventsForwardAsync($stream2, 0, 2, false);
            $this->assertCount(0, $slice->events());

            $connection->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_create_stream_with_no_stream_exp_ver_on_first_write_if_does_not_exist(): void
    {
        wait(call(function () {
            $stream = 'should_create_stream_with_no_stream_exp_ver_on_first_write_if_does_not_exist';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            /** @var WriteResult $result */
            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::NO_STREAM, [TestEvent::new()]);
            $this->assertSame(0, $result->nextExpectedVersion());

            /** @var StreamEventsSlice $slice */
            $slice = yield $connection->readStreamEventsForwardAsync($stream, 0, 2, false);

            $this->assertCount(1, $slice->events());

            $connection->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_create_stream_with_any_exp_ver_on_first_write_if_does_not_exist(): void
    {
        wait(call(function () {
            $stream = 'should_create_stream_with_any_exp_ver_on_first_write_if_does_not_exist';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            /** @var WriteResult $result */
            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::ANY, [TestEvent::new()]);
            $this->assertSame(0, $result->nextExpectedVersion());

            /** @var StreamEventsSlice $slice */
            $slice = yield $connection->readStreamEventsForwardAsync($stream, 0, 2, false);
            $this->assertCount(1, $slice->events());

            $connection->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function multiple_idempotent_writes(): void
    {
        wait(call(function () {
            $stream = 'multiple_idempotent_writes';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            $events = [TestEvent::new(), TestEvent::new(), TestEvent::new(), TestEvent::new()];

            /** @var WriteResult $result */
            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::ANY, $events);
            $this->assertSame(3, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::ANY, $events);
            $this->assertSame(3, $result->nextExpectedVersion());

            $connection->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function multiple_idempotent_writes_with_same_id_bug_case(): void
    {
        wait(call(function () {
            $stream = 'multiple_idempotent_writes_with_same_id_bug_case';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            $x = TestEvent::new();
            $events = [$x, $x, $x, $x, $x, $x];

            /** @var WriteResult $result */
            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::ANY, $events);
            $this->assertSame(5, $result->nextExpectedVersion());

            $connection->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function in_wtf_multiple_case_of_multiple_writes_expected_version_any_per_all_same_id(): void
    {
        wait(call(function () {
            $stream = 'in_wtf_multiple_case_of_multiple_writes_expected_version_any_per_all_same_id';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            $x = TestEvent::new();
            $events = [$x, $x, $x, $x, $x, $x];

            /** @var WriteResult $result */
            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::ANY, $events);
            $this->assertSame(5, $result->nextExpectedVersion());

            /** @var WriteResult $f */
            $f = yield $connection->appendToStreamAsync($stream, ExpectedVersion::ANY, $events);
            $this->assertSame(0, $f->nextExpectedVersion());

            $connection->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function in_slightly_reasonable_multiple_case_of_multiple_writes_with_expected_version_per_all_same_id(): void
    {
        wait(call(function () {
            $stream = 'in_slightly_reasonable_multiple_case_of_multiple_writes_with_expected_version_per_all_same_id';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            $x = TestEvent::new();
            $events = [$x, $x, $x, $x, $x, $x];

            /** @var WriteResult $result */
            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, $events);
            $this->assertSame(5, $result->nextExpectedVersion());

            /** @var WriteResult $f */
            $f = yield $connection->appendToStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, $events);
            $this->assertSame(5, $f->nextExpectedVersion());

            $connection->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_fail_writing_with_correct_exp_ver_to_deleted_stream(): void
    {
        wait(call(function () {
            $stream = 'should_fail_writing_with_correct_exp_ver_to_deleted_stream';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            yield $connection->deleteStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, true);

            try {
                $this->expectException(StreamDeletedException::class);
                yield $connection->appendToStreamAsync($stream, ExpectedVersion::NO_STREAM, [TestEvent::new()]);
            } finally {
                $connection->close();
            }
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_return_log_position_when_writing(): void
    {
        wait(call(function () {
            $stream = 'should_return_log_position_when_writing';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            /** @var WriteResult $result */
            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, [TestEvent::new()]);
            $this->assertGreaterThan(0, $result->logPosition()->preparePosition());
            $this->assertGreaterThan(0, $result->logPosition()->commitPosition());

            $connection->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_fail_writing_with_any_exp_ver_to_deleted_stream(): void
    {
        wait(call(function () {
            $stream = 'should_fail_writing_with_any_exp_ver_to_deleted_stream';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            try {
                yield $connection->deleteStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, true);
            } catch (Throwable $e) {
                $this->fail($e->getMessage());
            }

            try {
                $this->expectException(StreamDeletedException::class);
                yield $connection->appendToStreamAsync($stream, ExpectedVersion::ANY, [TestEvent::new()]);
            } finally {
                $connection->close();
            }
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_fail_writing_with_invalid_exp_ver_to_deleted_stream(): void
    {
        wait(call(function () {
            $stream = 'should_fail_writing_with_invalid_exp_ver_to_deleted_stream';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            yield $connection->deleteStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, true);

            $this->expectException(StreamDeletedException::class);
            try {
                yield $connection->appendToStreamAsync($stream, 5, [TestEvent::new()]);
            } finally {
                $connection->close();
            }
        }));
    }

    /**
     * @test
     * @doesNotPerformAssertions
     * @throws Throwable
     */
    public function should_append_with_correct_exp_ver_to_existing_stream(): void
    {
        wait(call(function () {
            $stream = 'should_append_with_correct_exp_ver_to_existing_stream';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            yield $connection->appendToStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, [TestEvent::new()]);

            $connection->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_append_with_any_exp_ver_to_existing_stream(): void
    {
        wait(call(function () {
            $stream = 'should_append_with_any_exp_ver_to_existing_stream';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            /** @var WriteResult $result */
            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, [TestEvent::new()]);
            $this->assertSame(0, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::ANY, [TestEvent::new()]);
            $this->assertSame(1, $result->nextExpectedVersion());

            $connection->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_fail_appending_with_wrong_exp_ver_to_existing_stream(): void
    {
        wait(call(function () {
            $stream = 'should_fail_appending_with_wrong_exp_ver_to_existing_stream';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            try {
                $this->expectException(WrongExpectedVersionException::class);
                yield $connection->appendToStreamAsync($stream, 1, [TestEvent::new()]);
            } finally {
                $connection->close();
            }
        }));
    }

    /**
     * @test
     * @doesNotPerformAssertions
     * @throws Throwable
     */
    public function should_append_with_stream_exists_exp_ver_to_existing_stream(): void
    {
        wait(call(function () {
            $stream = 'should_append_with_stream_exists_exp_ver_to_existing_stream';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            yield $connection->appendToStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, [TestEvent::new()]);
            yield $connection->appendToStreamAsync($stream, ExpectedVersion::STREAM_EXISTS, [TestEvent::new()]);

            $connection->close();
        }));
    }

    /**
     * @test
     * @doesNotPerformAssertions
     * @throws Throwable
     */
    public function should_append_with_stream_exists_exp_ver_to_stream_with_multiple_events(): void
    {
        wait(call(function () {
            $stream = 'should_append_with_stream_exists_exp_ver_to_stream_with_multiple_events';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            for ($i = 0; $i < 5; $i++) {
                yield $connection->appendToStreamAsync($stream, ExpectedVersion::ANY, [TestEvent::new()]);
            }

            yield $connection->appendToStreamAsync($stream, ExpectedVersion::STREAM_EXISTS, [TestEvent::new()]);

            $connection->close();
        }));
    }

    /**
     * @test
     * @doesNotPerformAssertions
     * @throws Throwable
     */
    public function should_append_with_stream_exists_exp_ver_if_metadata_stream_exists(): void
    {
        wait(call(function () {
            $stream = 'should_append_with_stream_exists_exp_ver_if_metadata_stream_exists';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            yield $connection->setStreamMetadataAsync(
                $stream,
                ExpectedVersion::ANY,
                new StreamMetadata(
                    10
                )
            );

            yield  $connection->appendToStreamAsync($stream, ExpectedVersion::STREAM_EXISTS, [TestEvent::new()]);

            $connection->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_fail_appending_with_stream_exists_exp_ver_and_stream_does_not_exist(): void
    {
        wait(call(function () {
            $stream = 'should_fail_appending_with_stream_exists_exp_ver_and_stream_does_not_exist';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            try {
                $this->expectException(WrongExpectedVersionException::class);
                yield $connection->appendToStreamAsync($stream, ExpectedVersion::STREAM_EXISTS, [TestEvent::new()]);
            } finally {
                $connection->close();
            }
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_fail_appending_with_stream_exists_exp_ver_to_hard_deleted_stream(): void
    {
        wait(call(function () {
            $stream = 'should_fail_appending_with_stream_exists_exp_ver_and_stream_does_not_exist';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            yield $connection->deleteStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, true);

            try {
                $this->expectException(StreamDeletedException::class);
                yield $connection->appendToStreamAsync($stream, ExpectedVersion::STREAM_EXISTS, [TestEvent::new()]);
            } finally {
                $connection->close();
            }
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_fail_appending_with_stream_exists_exp_ver_to_soft_deleted_stream(): void
    {
        wait(call(function () {
            $stream = 'should_fail_appending_with_stream_exists_exp_ver_to_soft_deleted_stream';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            yield $connection->deleteStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, false);

            try {
                $this->expectException(StreamDeletedException::class);
                yield $connection->appendToStreamAsync($stream, ExpectedVersion::STREAM_EXISTS, [TestEvent::new()]);
            } finally {
                $connection->close();
            }
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function can_append_multiple_events_at_once(): void
    {
        wait(call(function () {
            $stream = 'can_append_multiple_events_at_once';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            $events = TestEvent::newAmount(100);

            /** @var WriteResult $result */
            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, $events);

            $this->assertSame(99, $result->nextExpectedVersion());

            $connection->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function returns_failure_status_when_conditionally_appending_with_version_mismatch(): void
    {
        wait(call(function () {
            $stream = 'returns_failure_status_when_conditionally_appending_with_version_mismatch';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            /** @var ConditionalWriteResult $result */
            $result = yield $connection->conditionalAppendToStreamAsync($stream, 7, [TestEvent::new()]);

            $this->assertTrue($result->status()->equals(ConditionalWriteStatus::versionMismatch()));

            $connection->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function returns_success_status_when_conditionally_appending_with_matching_version(): void
    {
        wait(call(function () {
            $stream = 'returns_success_status_when_conditionally_appending_with_matching_version';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            /** @var ConditionalWriteResult $result */
            $result = yield $connection->conditionalAppendToStreamAsync($stream, ExpectedVersion::ANY, [TestEvent::new()]);

            $this->assertTrue($result->status()->equals(ConditionalWriteStatus::succeeded()));
            $this->assertNotNull($result->logPosition());
            $this->assertNotNull($result->nextExpectedVersion());

            $connection->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function returns_failure_status_when_conditionally_appending_to_a_deleted_stream(): void
    {
        wait(call(function () {
            $stream = 'returns_failure_status_when_conditionally_appending_to_a_deleted_stream';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            yield $connection->appendToStreamAsync($stream, ExpectedVersion::ANY, [TestEvent::new()]);

            yield $connection->deleteStreamAsync($stream, ExpectedVersion::ANY, true);

            /** @var ConditionalWriteResult $result */
            $result = yield $connection->conditionalAppendToStreamAsync($stream, ExpectedVersion::ANY, [TestEvent::new()]);

            $this->assertTrue($result->status()->equals(ConditionalWriteStatus::streamDeleted()));

            $connection->close();
        }));
    }
}
