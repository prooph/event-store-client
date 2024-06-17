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

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Prooph\EventStore\ConditionalWriteStatus;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\ReadDirection;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\Util\Json;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;

class append_to_stream extends EventStoreConnectionTestCase
{
    /** @test */
    public function cannot_append_to_stream_without_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->connection->appendToStream('', ExpectedVersion::Any, []);
    }

    /** @test */
    public function should_allow_appending_zero_events_to_stream_with_no_problems(): void
    {
        $stream1 = 'should_allow_appending_zero_events_to_stream_with_no_problems1';
        $stream2 = 'should_allow_appending_zero_events_to_stream_with_no_problems2';

        $result = $this->connection->appendToStream($stream1, ExpectedVersion::Any, []);
        $this->assertSame(-1, $result->nextExpectedVersion());

        $result = $this->connection->appendToStream($stream1, ExpectedVersion::NoStream, []);
        $this->assertSame(-1, $result->nextExpectedVersion());

        $result = $this->connection->appendToStream($stream1, ExpectedVersion::Any, []);
        $this->assertSame(-1, $result->nextExpectedVersion());

        $result = $this->connection->appendToStream($stream1, ExpectedVersion::NoStream, []);
        $this->assertSame(-1, $result->nextExpectedVersion());

        $slice = $this->connection->readStreamEventsForward($stream1, 0, 2, false);
        $this->assertCount(0, $slice->events());
        $this->assertSame($stream1, $slice->stream());
        $this->assertSame(0, $slice->fromEventNumber());
        $this->assertSame($slice->readDirection(), ReadDirection::Forward);

        $result = $this->connection->appendToStream($stream2, ExpectedVersion::NoStream, []);
        $this->assertSame(-1, $result->nextExpectedVersion());

        $result = $this->connection->appendToStream($stream2, ExpectedVersion::Any, []);
        $this->assertSame(-1, $result->nextExpectedVersion());

        $result = $this->connection->appendToStream($stream2, ExpectedVersion::NoStream, []);
        $this->assertSame(-1, $result->nextExpectedVersion());

        $result = $this->connection->appendToStream($stream2, ExpectedVersion::Any, []);
        $this->assertSame(-1, $result->nextExpectedVersion());

        $slice = $this->connection->readStreamEventsForward($stream2, 0, 2, false);
        $this->assertCount(0, $slice->events());
    }

    /** @test */
    public function should_create_stream_with_no_stream_exp_ver_on_first_write_if_does_not_exist(): void
    {
        $stream = 'should_create_stream_with_no_stream_exp_ver_on_first_write_if_does_not_exist';

        $result = $this->connection->appendToStream($stream, ExpectedVersion::NoStream, [TestEvent::newTestEvent()]);
        $this->assertSame(0, $result->nextExpectedVersion());

        $slice = $this->connection->readStreamEventsForward($stream, 0, 2, false);

        $this->assertCount(1, $slice->events());
    }

    /** @test */
    public function should_create_stream_with_any_exp_ver_on_first_write_if_does_not_exist(): void
    {
        $stream = 'should_create_stream_with_any_exp_ver_on_first_write_if_does_not_exist';

        $result = $this->connection->appendToStream($stream, ExpectedVersion::Any, [TestEvent::newTestEvent()]);
        $this->assertSame(0, $result->nextExpectedVersion());

        $slice = $this->connection->readStreamEventsForward($stream, 0, 2, false);
        $this->assertCount(1, $slice->events());
    }

    /** @test */
    public function multiple_idempotent_writes(): void
    {
        $stream = 'multiple_idempotent_writes';

        $events = [TestEvent::newTestEvent(), TestEvent::newTestEvent(), TestEvent::newTestEvent(), TestEvent::newTestEvent()];

        $result = $this->connection->appendToStream($stream, ExpectedVersion::Any, $events);
        $this->assertSame(3, $result->nextExpectedVersion());

        $result = $this->connection->appendToStream($stream, ExpectedVersion::Any, $events);
        $this->assertSame(3, $result->nextExpectedVersion());
    }

    /** @test */
    public function multiple_idempotent_writes_with_same_id_bug_case(): void
    {
        $stream = 'multiple_idempotent_writes_with_same_id_bug_case';

        $x = TestEvent::newTestEvent();
        $events = [$x, $x, $x, $x, $x, $x];

        $result = $this->connection->appendToStream($stream, ExpectedVersion::Any, $events);
        $this->assertSame(5, $result->nextExpectedVersion());
    }

    /** @test */
    public function in_wtf_multiple_case_of_multiple_writes_expected_version_any_per_all_same_id(): void
    {
        $stream = 'in_wtf_multiple_case_of_multiple_writes_expected_version_any_per_all_same_id';

        $x = TestEvent::newTestEvent();
        $events = [$x, $x, $x, $x, $x, $x];

        $result = $this->connection->appendToStream($stream, ExpectedVersion::Any, $events);
        $this->assertSame(5, $result->nextExpectedVersion());

        $f = $this->connection->appendToStream($stream, ExpectedVersion::Any, $events);
        $this->assertSame(0, $f->nextExpectedVersion());
    }

    /** @test */
    public function in_slightly_reasonable_multiple_case_of_multiple_writes_with_expected_version_per_all_same_id(): void
    {
        $stream = 'in_slightly_reasonable_multiple_case_of_multiple_writes_with_expected_version_per_all_same_id';

        $x = TestEvent::newTestEvent();
        $events = [$x, $x, $x, $x, $x, $x];

        $result = $this->connection->appendToStream($stream, ExpectedVersion::NoStream, $events);
        $this->assertSame(5, $result->nextExpectedVersion());

        $f = $this->connection->appendToStream($stream, ExpectedVersion::NoStream, $events);
        $this->assertSame(5, $f->nextExpectedVersion());
    }

    /** @test */
    public function should_fail_writing_with_correct_exp_ver_to_deleted_stream(): void
    {
        $stream = 'should_fail_writing_with_correct_exp_ver_to_deleted_stream';

        $this->connection->deleteStream($stream, ExpectedVersion::NoStream, true);

        $this->expectException(StreamDeleted::class);

        $this->connection->appendToStream($stream, ExpectedVersion::NoStream, [TestEvent::newTestEvent()]);
    }

    /** @test */
    public function should_return_log_position_when_writing(): void
    {
        $stream = 'should_return_log_position_when_writing';

        $result = $this->connection->appendToStream($stream, ExpectedVersion::NoStream, [TestEvent::newTestEvent()]);
        $this->assertGreaterThan(0, $result->logPosition()->preparePosition());
        $this->assertGreaterThan(0, $result->logPosition()->commitPosition());
    }

    /** @test */
    public function should_fail_writing_with_any_exp_ver_to_deleted_stream(): void
    {
        $stream = 'should_fail_writing_with_any_exp_ver_to_deleted_stream';

        try {
            $this->connection->deleteStream($stream, ExpectedVersion::NoStream, true);
        } catch (Throwable $e) {
            $this->fail($e->getMessage());
        }

        $this->expectException(StreamDeleted::class);

        $this->connection->appendToStream($stream, ExpectedVersion::Any, [TestEvent::newTestEvent()]);
    }

    /** @test */
    public function should_fail_writing_with_invalid_exp_ver_to_deleted_stream(): void
    {
        $stream = 'should_fail_writing_with_invalid_exp_ver_to_deleted_stream';

        $this->connection->deleteStream($stream, ExpectedVersion::NoStream, true);

        $this->expectException(StreamDeleted::class);

        $this->connection->appendToStream($stream, 5, [TestEvent::newTestEvent()]);
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function should_append_with_correct_exp_ver_to_existing_stream(): void
    {
        $stream = 'should_append_with_correct_exp_ver_to_existing_stream';

        $this->connection->appendToStream($stream, ExpectedVersion::NoStream, [TestEvent::newTestEvent()]);
    }

    /** @test */
    public function should_append_with_any_exp_ver_to_existing_stream(): void
    {
        $stream = 'should_append_with_any_exp_ver_to_existing_stream';

        $result = $this->connection->appendToStream($stream, ExpectedVersion::NoStream, [TestEvent::newTestEvent()]);
        $this->assertSame(0, $result->nextExpectedVersion());

        $result = $this->connection->appendToStream($stream, ExpectedVersion::Any, [TestEvent::newTestEvent()]);
        $this->assertSame(1, $result->nextExpectedVersion());
    }

    /** @test */
    public function should_fail_appending_with_wrong_exp_ver_to_existing_stream(): void
    {
        $stream = 'should_fail_appending_with_wrong_exp_ver_to_existing_stream';

        $this->expectException(WrongExpectedVersion::class);

        $this->connection->appendToStream($stream, 1, [TestEvent::newTestEvent()]);
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function should_append_with_stream_exists_exp_ver_to_existing_stream(): void
    {
        $stream = 'should_append_with_stream_exists_exp_ver_to_existing_stream';

        $this->connection->appendToStream($stream, ExpectedVersion::NoStream, [TestEvent::newTestEvent()]);
        $this->connection->appendToStream($stream, ExpectedVersion::StreamExists, [TestEvent::newTestEvent()]);
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function should_append_with_stream_exists_exp_ver_to_stream_with_multiple_events(): void
    {
        $stream = 'should_append_with_stream_exists_exp_ver_to_stream_with_multiple_events';

        for ($i = 0; $i < 5; $i++) {
            $this->connection->appendToStream($stream, ExpectedVersion::Any, [TestEvent::newTestEvent()]);
        }

        $this->connection->appendToStream($stream, ExpectedVersion::StreamExists, [TestEvent::newTestEvent()]);
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function should_append_with_stream_exists_exp_ver_if_metadata_stream_exists(): void
    {
        $stream = 'should_append_with_stream_exists_exp_ver_if_metadata_stream_exists';

        $this->connection->setStreamMetadata(
            $stream,
            ExpectedVersion::Any,
            new StreamMetadata(
                10
            )
        );

        $this->connection->appendToStream($stream, ExpectedVersion::StreamExists, [TestEvent::newTestEvent()]);
    }

    /** @test */
    public function should_fail_appending_with_stream_exists_exp_ver_and_stream_does_not_exist(): void
    {
        $stream = 'should_fail_appending_with_stream_exists_exp_ver_and_stream_does_not_exist';

        $this->expectException(WrongExpectedVersion::class);

        $this->connection->appendToStream($stream, ExpectedVersion::StreamExists, [TestEvent::newTestEvent()]);
    }

    /** @test */
    public function should_fail_appending_with_stream_exists_exp_ver_to_hard_deleted_stream(): void
    {
        $stream = 'should_fail_appending_with_stream_exists_exp_ver_and_stream_does_not_exist';

        $this->connection->deleteStream($stream, ExpectedVersion::NoStream, true);

        $this->expectException(StreamDeleted::class);

        $this->connection->appendToStream($stream, ExpectedVersion::StreamExists, [TestEvent::newTestEvent()]);
    }

    /** @test */
    public function should_fail_appending_with_stream_exists_exp_ver_to_soft_deleted_stream(): void
    {
        $stream = 'should_fail_appending_with_stream_exists_exp_ver_to_soft_deleted_stream';

        $this->connection->deleteStream($stream, ExpectedVersion::NoStream, false);

        $this->expectException(StreamDeleted::class);

        $this->connection->appendToStream($stream, ExpectedVersion::StreamExists, [TestEvent::newTestEvent()]);
    }

    /** @test */
    public function can_append_multiple_events_at_once(): void
    {
        $stream = 'can_append_multiple_events_at_once';

        $events = TestEvent::newAmount(100);

        $result = $this->connection->appendToStream($stream, ExpectedVersion::NoStream, $events);

        $this->assertSame(99, $result->nextExpectedVersion());
    }

    /** @test */
    public function returns_failure_status_when_conditionally_appending_with_version_mismatch(): void
    {
        $stream = 'returns_failure_status_when_conditionally_appending_with_version_mismatch';

        $result = $this->connection->conditionalAppendToStream($stream, 7, [TestEvent::newTestEvent()]);

        $this->assertSame($result->status(), ConditionalWriteStatus::VersionMismatch);
    }

    /** @test */
    public function returns_success_status_when_conditionally_appending_with_matching_version(): void
    {
        $stream = 'returns_success_status_when_conditionally_appending_with_matching_version';

        $result = $this->connection->conditionalAppendToStream($stream, ExpectedVersion::Any, [TestEvent::newTestEvent()]);

        $this->assertSame($result->status(), ConditionalWriteStatus::Succeeded);
        $this->assertNotNull($result->logPosition());
        $this->assertNotNull($result->nextExpectedVersion());
    }

    /** @test */
    public function returns_failure_status_when_conditionally_appending_to_a_deleted_stream(): void
    {
        $stream = 'returns_failure_status_when_conditionally_appending_to_a_deleted_stream';

        $this->connection->appendToStream($stream, ExpectedVersion::Any, [TestEvent::newTestEvent()]);

        $this->connection->deleteStream($stream, ExpectedVersion::Any, true);

        $result = $this->connection->conditionalAppendToStream($stream, ExpectedVersion::Any, [TestEvent::newTestEvent()]);

        $this->assertSame($result->status(), ConditionalWriteStatus::StreamDeleted);
    }

    /** @test */
    public function writes_predefined_event_id(): void
    {
        $stream = 'writes_predefined_event_id';

        $event = TestEvent::newTestEvent();

        $this->connection->appendToStream($stream, ExpectedVersion::Any, [$event]);

        $events = $this->connection->readStreamEventsBackward($stream, -1, 1);

        $readEvent = $events->events()[0]->event();

        $this->connection->close();

        $this->assertSame($event->eventId()->toString(), $readEvent->eventId()->toString());

        $url = \sprintf(
            'http://%s:%s/streams/%s/head/backward/1?embed=body',
            \getenv('ES_HOST'),
            \getenv('ES_HTTP_PORT'),
            $stream
        );

        $httpAuthentication = \sprintf(
            '%s:%s',
            DefaultData::adminUsername(),
            DefaultData::adminPassword()
        );

        $encodedCredentials = \base64_encode($httpAuthentication);

        $request = new Request($url, 'GET');
        $request->addHeader('Accept', 'application/vnd.eventstore.atom+json');
        $request->setHeader('Authorization', 'Basic ' . $encodedCredentials);

        $httpClient = HttpClientBuilder::buildDefault();

        $response = $httpClient->request($request);

        $this->assertSame(200, $response->getStatus());

        $body = $response->getBody()->read();

        $json = Json::decode($body);

        $eventId = $json['entries'][0]['eventId'];

        $this->assertSame($event->eventId()->toString(), $eventId);
    }
}
