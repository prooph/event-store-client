<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\Artax\DefaultClient;
use Amp\Artax\Request;
use Amp\Artax\Response;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\ConditionalWriteResult;
use Prooph\EventStoreClient\ConditionalWriteStatus;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Exception\StreamDeletedException;
use Prooph\EventStoreClient\Exception\WrongExpectedVersionException;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\ReadDirection;
use Prooph\EventStoreClient\StreamEventsSlice;
use Prooph\EventStoreClient\StreamMetadata;
use Prooph\EventStoreClient\Util\Json;
use Prooph\EventStoreClient\WriteResult;
use ProophTest\EventStoreClient\Helper\TestConnection;
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
            $connection = TestConnection::create();
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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            $result = yield $connection->appendToStreamAsync($stream1, ExpectedVersion::ANY, []);
            \assert($result instanceof WriteResult);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream1, ExpectedVersion::NO_STREAM, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream1, ExpectedVersion::ANY, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream1, ExpectedVersion::NO_STREAM, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $slice = yield $connection->readStreamEventsForwardAsync($stream1, 0, 2, false);
            \assert($slice instanceof StreamEventsSlice);
            $this->assertCount(0, $slice->events());
            $this->assertEquals($stream1, $slice->stream());
            $this->assertEquals(0, $slice->fromEventNumber());
            $this->assertEquals($slice->readDirection()->name(), ReadDirection::forward()->name());

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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::NO_STREAM, [TestEvent::newTestEvent()]);
            \assert($result instanceof WriteResult);
            $this->assertSame(0, $result->nextExpectedVersion());

            $slice = yield $connection->readStreamEventsForwardAsync($stream, 0, 2, false);
            \assert($slice instanceof StreamEventsSlice);

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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::ANY, [TestEvent::newTestEvent()]);
            \assert($result instanceof WriteResult);
            $this->assertSame(0, $result->nextExpectedVersion());

            $slice = yield $connection->readStreamEventsForwardAsync($stream, 0, 2, false);
            \assert($slice instanceof StreamEventsSlice);
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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            $events = [TestEvent::newTestEvent(), TestEvent::newTestEvent(), TestEvent::newTestEvent(), TestEvent::newTestEvent()];

            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::ANY, $events);
            \assert($result instanceof WriteResult);
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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            $x = TestEvent::newTestEvent();
            $events = [$x, $x, $x, $x, $x, $x];

            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::ANY, $events);
            \assert($result instanceof WriteResult);
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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            $x = TestEvent::newTestEvent();
            $events = [$x, $x, $x, $x, $x, $x];

            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::ANY, $events);
            \assert($result instanceof WriteResult);
            $this->assertSame(5, $result->nextExpectedVersion());

            $f = yield $connection->appendToStreamAsync($stream, ExpectedVersion::ANY, $events);
            \assert($f instanceof WriteResult);
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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            $x = TestEvent::newTestEvent();
            $events = [$x, $x, $x, $x, $x, $x];

            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, $events);
            \assert($result instanceof WriteResult);
            $this->assertSame(5, $result->nextExpectedVersion());

            $f = yield $connection->appendToStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, $events);
            \assert($f instanceof WriteResult);
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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            yield $connection->deleteStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, true);

            try {
                $this->expectException(StreamDeletedException::class);
                yield $connection->appendToStreamAsync($stream, ExpectedVersion::NO_STREAM, [TestEvent::newTestEvent()]);
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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, [TestEvent::newTestEvent()]);
            \assert($result instanceof WriteResult);
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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            try {
                yield $connection->deleteStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, true);
            } catch (Throwable $e) {
                $this->fail($e->getMessage());
            }

            try {
                $this->expectException(StreamDeletedException::class);
                yield $connection->appendToStreamAsync($stream, ExpectedVersion::ANY, [TestEvent::newTestEvent()]);
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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            yield $connection->deleteStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, true);

            $this->expectException(StreamDeletedException::class);
            try {
                yield $connection->appendToStreamAsync($stream, 5, [TestEvent::newTestEvent()]);
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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            yield $connection->appendToStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, [TestEvent::newTestEvent()]);

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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, [TestEvent::newTestEvent()]);
            \assert($result instanceof WriteResult);
            $this->assertSame(0, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::ANY, [TestEvent::newTestEvent()]);
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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            try {
                $this->expectException(WrongExpectedVersionException::class);
                yield $connection->appendToStreamAsync($stream, 1, [TestEvent::newTestEvent()]);
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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            yield $connection->appendToStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, [TestEvent::newTestEvent()]);
            yield $connection->appendToStreamAsync($stream, ExpectedVersion::STREAM_EXISTS, [TestEvent::newTestEvent()]);

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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            for ($i = 0; $i < 5; $i++) {
                yield $connection->appendToStreamAsync($stream, ExpectedVersion::ANY, [TestEvent::newTestEvent()]);
            }

            yield $connection->appendToStreamAsync($stream, ExpectedVersion::STREAM_EXISTS, [TestEvent::newTestEvent()]);

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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            yield $connection->setStreamMetadataAsync(
                $stream,
                ExpectedVersion::ANY,
                new StreamMetadata(
                    10
                )
            );

            yield  $connection->appendToStreamAsync($stream, ExpectedVersion::STREAM_EXISTS, [TestEvent::newTestEvent()]);

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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            try {
                $this->expectException(WrongExpectedVersionException::class);
                yield $connection->appendToStreamAsync($stream, ExpectedVersion::STREAM_EXISTS, [TestEvent::newTestEvent()]);
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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            yield $connection->deleteStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, true);

            try {
                $this->expectException(StreamDeletedException::class);
                yield $connection->appendToStreamAsync($stream, ExpectedVersion::STREAM_EXISTS, [TestEvent::newTestEvent()]);
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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            yield $connection->deleteStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, false);

            try {
                $this->expectException(StreamDeletedException::class);
                yield $connection->appendToStreamAsync($stream, ExpectedVersion::STREAM_EXISTS, [TestEvent::newTestEvent()]);
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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            $events = TestEvent::newAmount(100);

            $result = yield $connection->appendToStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, $events);
            \assert($result instanceof WriteResult);

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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            $result = yield $connection->conditionalAppendToStreamAsync($stream, 7, [TestEvent::newTestEvent()]);
            \assert($result instanceof ConditionalWriteResult);

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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            $result = yield $connection->conditionalAppendToStreamAsync($stream, ExpectedVersion::ANY, [TestEvent::newTestEvent()]);
            \assert($result instanceof ConditionalWriteResult);

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

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            yield $connection->appendToStreamAsync($stream, ExpectedVersion::ANY, [TestEvent::newTestEvent()]);

            yield $connection->deleteStreamAsync($stream, ExpectedVersion::ANY, true);

            $result = yield $connection->conditionalAppendToStreamAsync($stream, ExpectedVersion::ANY, [TestEvent::newTestEvent()]);
            \assert($result instanceof ConditionalWriteResult);

            $this->assertTrue($result->status()->equals(ConditionalWriteStatus::streamDeleted()));

            $connection->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function writes_predefined_event_id(): void
    {
        wait(call(function () {
            $stream = 'writes_predefined_event_id';

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            $event = TestEvent::newTestEvent();

            yield $connection->appendToStreamAsync($stream, ExpectedVersion::ANY, [$event]);

            $events = yield $connection->readStreamEventsBackwardAsync($stream, -1, 1);
            \assert($events instanceof StreamEventsSlice);

            $readEvent = $events->events()[0]->event();

            $connection->close();

            $this->assertEquals($event->eventId()->toString(), $readEvent->eventId()->toString());

            $url = \sprintf(
                'http://%s:%s/streams/%s/head/backward/1?embed=body',
                \getenv('ES_HOST'),
                \getenv('ES_HTTP_PORT'),
                $stream
            );

            $request = new Request($url, 'GET');
            $request = $request->withAddedHeader('Accept', 'application/vnd.eventstore.atom+json');

            $client = new DefaultClient();

            $response = yield $client->request($request);

            \assert($response instanceof Response);

            $body = yield $response->getBody()->read();

            $json = Json::decode($body);

            $eventId = $json['entries'][0]['eventId'];

            $this->assertEquals($event->eventId()->toString(), $eventId);
        }));
    }
}
