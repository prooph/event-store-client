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

use function Amp\call;
use Amp\Promise;
use Generator;
use Prooph\EventStore\EventData;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\StreamMetadata;
use ProophTest\EventStoreClient\Helper\TestEvent;

class when_having_max_count_set_for_stream extends EventStoreConnectionTestCase
{
    private string $stream = 'max-count-test-stream';
    /** @var EventData[] */
    private array $testEvents = [];

    protected function setUpAsync(): Promise
    {
        return call(function (): Generator {
            yield parent::setUpAsync();

            yield $this->connection->setStreamMetadataAsync(
                $this->stream,
                ExpectedVersion::ANY,
                StreamMetadata::create()->setMaxCount(3)->build(),
                DefaultData::adminCredentials()
            );

            for ($i = 0; $i < 5; $i++) {
                $this->testEvents[] = TestEvent::newTestEvent(null, (string) $i);
            }

            yield $this->connection->appendToStreamAsync(
                $this->stream,
                ExpectedVersion::ANY,
                $this->testEvents,
                DefaultData::adminCredentials()
            );
        });
    }

    /** @test */
    public function read_stream_forward_respects_max_count(): Generator
    {
        $res = yield $this->connection->readStreamEventsForwardAsync(
            $this->stream,
            0,
            100,
            false,
            DefaultData::adminCredentials()
        );
        \assert($res instanceof StreamEventsSlice);

        $this->assertTrue($res->status()->equals(SliceReadStatus::success()));
        $this->assertCount(3, $res->events());

        for ($i = 0; $i < 3; $i++) {
            $testEvent = $this->testEvents[$i + 2];
            $event = $res->events()[$i];

            $this->assertTrue($testEvent->eventId()->equals($event->event()->eventId()));
        }
    }

    /** @test */
    public function read_stream_backward_respects_max_count(): Generator
    {
        $res = yield $this->connection->readStreamEventsBackwardAsync(
            $this->stream,
            -1,
            100,
            false,
            DefaultData::adminCredentials()
        );
        \assert($res instanceof StreamEventsSlice);

        $this->assertTrue($res->status()->equals(SliceReadStatus::success()));
        $this->assertCount(3, $res->events());

        for ($i = 0; $i < 3; $i++) {
            $testEvent = $this->testEvents[$i + 2];
            $event = \array_reverse($res->events())[$i];

            $this->assertTrue($testEvent->eventId()->equals($event->event()->eventId()));
        }
    }

    /** @test */
    public function after_setting_less_strict_max_count_read_stream_forward_reads_more_events(): Generator
    {
        $res = yield $this->connection->readStreamEventsForwardAsync(
            $this->stream,
            0,
            100,
            false,
            DefaultData::adminCredentials()
        );
        \assert($res instanceof StreamEventsSlice);

        $this->assertTrue($res->status()->equals(SliceReadStatus::success()));
        $this->assertCount(3, $res->events());

        for ($i = 0; $i < 3; $i++) {
            $testEvent = $this->testEvents[$i + 2];
            $event = $res->events()[$i];

            $this->assertTrue($testEvent->eventId()->equals($event->event()->eventId()));
        }

        yield $this->connection->setStreamMetadataAsync(
            $this->stream,
            ExpectedVersion::ANY,
            StreamMetadata::create()->setMaxCount(4)->build(),
            DefaultData::adminCredentials()
        );

        $res = yield $this->connection->readStreamEventsForwardAsync(
            $this->stream,
            0,
            100,
            false,
            DefaultData::adminCredentials()
        );
        \assert($res instanceof StreamEventsSlice);

        $this->assertTrue($res->status()->equals(SliceReadStatus::success()));
        $this->assertCount(4, $res->events());

        for ($i = 0; $i < 4; $i++) {
            $testEvent = $this->testEvents[$i + 1];
            $event = $res->events()[$i];

            $this->assertTrue($testEvent->eventId()->equals($event->event()->eventId()));
        }
    }

    /** @test */
    public function after_setting_more_strict_max_count_read_stream_forward_reads_less_events(): Generator
    {
        $res = yield $this->connection->readStreamEventsForwardAsync(
            $this->stream,
            0,
            100,
            false,
            DefaultData::adminCredentials()
        );
        \assert($res instanceof StreamEventsSlice);

        $this->assertTrue($res->status()->equals(SliceReadStatus::success()));
        $this->assertCount(3, $res->events());

        for ($i = 0; $i < 3; $i++) {
            $testEvent = $this->testEvents[$i + 2];
            $event = $res->events()[$i];

            $this->assertTrue($testEvent->eventId()->equals($event->event()->eventId()));
        }

        yield $this->connection->setStreamMetadataAsync(
            $this->stream,
            ExpectedVersion::ANY,
            StreamMetadata::create()->setMaxCount(2)->build(),
            DefaultData::adminCredentials()
        );

        $res = yield $this->connection->readStreamEventsForwardAsync(
            $this->stream,
            0,
            100,
            false,
            DefaultData::adminCredentials()
        );
        \assert($res instanceof StreamEventsSlice);

        $this->assertTrue($res->status()->equals(SliceReadStatus::success()));
        $this->assertCount(2, $res->events());

        for ($i = 0; $i < 2; $i++) {
            $testEvent = $this->testEvents[$i + 3];
            $event = $res->events()[$i];

            $this->assertTrue($testEvent->eventId()->equals($event->event()->eventId()));
        }
    }

    /** @test */
    public function after_setting_less_strict_max_count_read_stream_backward_reads_more_events(): Generator
    {
        $res = yield $this->connection->readStreamEventsBackwardAsync(
            $this->stream,
            -1,
            100,
            false,
            DefaultData::adminCredentials()
        );
        \assert($res instanceof StreamEventsSlice);

        $this->assertTrue($res->status()->equals(SliceReadStatus::success()));
        $this->assertCount(3, $res->events());

        for ($i = 0; $i < 3; $i++) {
            $testEvent = $this->testEvents[$i + 2];
            $event = \array_reverse($res->events())[$i];

            $this->assertTrue($testEvent->eventId()->equals($event->event()->eventId()));
        }

        yield $this->connection->setStreamMetadataAsync(
            $this->stream,
            ExpectedVersion::ANY,
            StreamMetadata::create()->setMaxCount(4)->build(),
            DefaultData::adminCredentials()
        );

        $res = yield $this->connection->readStreamEventsBackwardAsync(
            $this->stream,
            -1,
            100,
            false,
            DefaultData::adminCredentials()
        );
        \assert($res instanceof StreamEventsSlice);

        $this->assertTrue($res->status()->equals(SliceReadStatus::success()));
        $this->assertCount(4, $res->events());

        for ($i = 0; $i < 4; $i++) {
            $testEvent = $this->testEvents[$i + 1];
            $event = \array_reverse($res->events())[$i];

            $this->assertTrue($testEvent->eventId()->equals($event->event()->eventId()));
        }
    }

    /** @test */
    public function after_setting_more_strict_max_count_read_stream_backward_reads_less_events(): Generator
    {
        $res = yield $this->connection->readStreamEventsBackwardAsync(
            $this->stream,
            -1,
            100,
            false,
            DefaultData::adminCredentials()
        );
        \assert($res instanceof StreamEventsSlice);

        $this->assertTrue($res->status()->equals(SliceReadStatus::success()));
        $this->assertCount(3, $res->events());

        for ($i = 0; $i < 3; $i++) {
            $testEvent = $this->testEvents[$i + 2];
            $event = \array_reverse($res->events())[$i];

            $this->assertTrue($testEvent->eventId()->equals($event->event()->eventId()));
        }

        yield $this->connection->setStreamMetadataAsync(
            $this->stream,
            ExpectedVersion::ANY,
            StreamMetadata::create()->setMaxCount(2)->build(),
            DefaultData::adminCredentials()
        );

        $res = yield $this->connection->readStreamEventsBackwardAsync(
            $this->stream,
            -1,
            100,
            false,
            DefaultData::adminCredentials()
        );
        \assert($res instanceof StreamEventsSlice);

        $this->assertTrue($res->status()->equals(SliceReadStatus::success()));
        $this->assertCount(2, $res->events());

        for ($i = 0; $i < 2; $i++) {
            $testEvent = $this->testEvents[$i + 3];
            $event = \array_reverse($res->events())[$i];

            $this->assertTrue($testEvent->eventId()->equals($event->event()->eventId()));
        }
    }
}
