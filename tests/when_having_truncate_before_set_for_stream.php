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

use Amp\PHPUnit\AsyncTestCase;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventReadResult;
use Prooph\EventStore\EventReadStatus;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\StreamMetadataBuilder;
use ProophTest\EventStoreClient\Helper\TestEvent;

class when_having_truncate_before_set_for_stream extends AsyncTestCase
{
    use SpecificationWithConnection;

    /** @var EventData[] */
    private array $testEvents = [];

    protected function setUp(): void
    {
        parent::setUp();

        for ($i = 0; $i < 5; $i++) {
            $this->testEvents[] = TestEvent::newTestEvent(null, (string) $i);
        }
    }

    private function appendEvents(string $stream): void
    {
        $this->connection->appendToStream(
            $stream,
            ExpectedVersion::NoStream,
            $this->testEvents,
            DefaultData::adminCredentials()
        );
    }

    private function setStreamMetadata(string $stream, int $expectedVersion, StreamMetadataBuilder $builder): void
    {
        $this->connection->setStreamMetadata(
            $stream,
            $expectedVersion,
            $builder->build(),
            DefaultData::adminCredentials()
        );
    }

    private function readEvent(string $stream, int $eventNumber): EventReadResult
    {
        return $this->connection->readEvent($stream, $eventNumber, false, DefaultData::adminCredentials());
    }

    private function assertSameEventIds(array $events, int $skip, bool $forward): void
    {
        $events = $forward ? $events : \array_reverse($events);

        foreach ($events as $index => $event) {
            $testEvent = $this->testEvents[$index + $skip];
            $this->assertTrue($testEvent->eventId()->equals($event->event()->eventId()));
        }
    }

    /** @test */
    public function read_event_respects_truncate_before(): void
    {
        $this->execute(function (): void {
            $stream = 'read_event_respects_truncate_before';

            $this->appendEvents($stream);
            $this->setStreamMetadata(
                $stream,
                ExpectedVersion::NoStream,
                StreamMetadata::create()->setTruncateBefore(2)
            );

            $res = $this->readEvent($stream, 1);
            $this->assertSame(EventReadStatus::NotFound, $res->status());

            $res = $this->readEvent($stream, 2);
            $this->assertSame(EventReadStatus::Success, $res->status());
            $this->assertTrue($this->testEvents[2]->eventId()->equals($res->event()->originalEvent()->eventId()));
        });
    }

    /** @test */
    public function read_stream_forward_respects_truncatebefore(): void
    {
        $this->execute(function (): void {
            $stream = 'read_stream_forward_respects_truncatebefore';

            $this->appendEvents($stream);
            $this->setStreamMetadata(
                $stream,
                ExpectedVersion::NoStream,
                StreamMetadata::create()->setTruncateBefore(2)
            );

            $res = $this->connection->readStreamEventsForward($stream, 0, 100, false, DefaultData::adminCredentials());
            $this->assertSame(SliceReadStatus::Success, $res->status());
            $this->assertCount(3, $res->events());

            $this->assertSameEventIds($res->events(), 2, true);
        });
    }

    /** @test */
    public function read_stream_backward_respects_truncatebefore(): void
    {
        $this->execute(function (): void {
            $stream = 'read_stream_backward_respects_truncatebefore';

            $this->appendEvents($stream);
            $this->setStreamMetadata(
                $stream,
                ExpectedVersion::NoStream,
                StreamMetadata::create()->setTruncateBefore(2)
            );

            $res = $this->connection->readStreamEventsBackward($stream, -1, 100, false, DefaultData::adminCredentials());
            $this->assertSame(SliceReadStatus::Success, $res->status());
            $this->assertCount(3, $res->events());

            $this->assertSameEventIds($res->events(), 2, false);
        });
    }

    /** @test */
    public function after_setting_less_strict_truncatebefore_read_event_reads_more_events(): void
    {
        $this->execute(function (): void {
            $stream = 'after_setting_less_strict_truncatebefore_read_event_reads_more_events';

            $this->appendEvents($stream);
            $this->setStreamMetadata(
                $stream,
                ExpectedVersion::NoStream,
                StreamMetadata::create()->setTruncateBefore(2)
            );

            $res = $this->readEvent($stream, 1);
            $this->assertSame(EventReadStatus::NotFound, $res->status());

            $res = $this->readEvent($stream, 2);
            $this->assertSame(EventReadStatus::Success, $res->status());
            $this->assertTrue($this->testEvents[2]->eventId()->equals($res->event()->originalEvent()->eventId()));

            $this->setStreamMetadata(
                $stream,
                0,
                StreamMetadata::create()->setTruncateBefore(1)
            );

            $res = $this->readEvent($stream, 0);
            $this->assertSame(EventReadStatus::NotFound, $res->status());

            $res = $this->readEvent($stream, 1);
            $this->assertSame(EventReadStatus::Success, $res->status());
            $this->assertTrue($this->testEvents[1]->eventId()->equals($res->event()->originalEvent()->eventId()));
        });
    }

    /** @test */
    public function after_setting_more_strict_truncatebefore_read_event_reads_less_events(): void
    {
        $this->execute(function (): void {
            $stream = 'after_setting_more_strict_truncate_before_read_event_reads_less_events';

            $this->appendEvents($stream);
            $this->setStreamMetadata(
                $stream,
                ExpectedVersion::NoStream,
                StreamMetadata::create()->setTruncateBefore(2)
            );

            $res = $this->readEvent($stream, 1);
            $this->assertSame(EventReadStatus::NotFound, $res->status());

            $res = $this->readEvent($stream, 2);
            $this->assertSame(EventReadStatus::Success, $res->status());
            $this->assertTrue($this->testEvents[2]->eventId()->equals($res->event()->originalEvent()->eventId()));

            $this->setStreamMetadata(
                $stream,
                0,
                StreamMetadata::create()->setTruncateBefore(3)
            );

            $res = $this->readEvent($stream, 2);
            $this->assertSame(EventReadStatus::NotFound, $res->status());

            $res = $this->readEvent($stream, 3);
            $this->assertSame(EventReadStatus::Success, $res->status());
            $this->assertTrue($this->testEvents[3]->eventId()->equals($res->event()->originalEvent()->eventId()));
        });
    }

    /** @test */
    public function less_strict_max_count_doesnt_change_anything_for_event_read(): void
    {
        $this->execute(function (): void {
            $stream = 'less_strict_max_count_doesnt_change_anything_for_event_read';

            $this->appendEvents($stream);
            $this->setStreamMetadata(
                $stream,
                ExpectedVersion::NoStream,
                StreamMetadata::create()->setTruncateBefore(2)
            );

            $res = $this->readEvent($stream, 1);
            $this->assertSame(EventReadStatus::NotFound, $res->status());

            $res = $this->readEvent($stream, 2);
            $this->assertSame(EventReadStatus::Success, $res->status());
            $this->assertTrue($this->testEvents[2]->eventId()->equals($res->event()->originalEvent()->eventId()));

            $this->setStreamMetadata(
                $stream,
                0,
                StreamMetadata::create()->setTruncateBefore(2)->setMaxCount(4)
            );

            $res = $this->readEvent($stream, 1);
            $this->assertSame(EventReadStatus::NotFound, $res->status());

            $res = $this->readEvent($stream, 2);
            $this->assertSame(EventReadStatus::Success, $res->status());
            $this->assertTrue($this->testEvents[2]->eventId()->equals($res->event()->originalEvent()->eventId()));
        });
    }

    /** @test */
    public function more_strict_max_count_gives_less_events_for_event_read(): void
    {
        $this->execute(function (): void {
            $stream = 'more_strict_max_count_gives_less_events_for_event_read';

            $this->appendEvents($stream);
            $this->setStreamMetadata(
                $stream,
                ExpectedVersion::NoStream,
                StreamMetadata::create()->setTruncateBefore(2)
            );

            $res = $this->readEvent($stream, 1);
            $this->assertSame(EventReadStatus::NotFound, $res->status());

            $res = $this->readEvent($stream, 2);
            $this->assertSame(EventReadStatus::Success, $res->status());
            $this->assertTrue($this->testEvents[2]->eventId()->equals($res->event()->originalEvent()->eventId()));

            $this->setStreamMetadata(
                $stream,
                0,
                StreamMetadata::create()->setTruncateBefore(2)->setMaxCount(2)
            );

            $res = $this->readEvent($stream, 2);
            $this->assertSame(EventReadStatus::NotFound, $res->status());

            $res = $this->readEvent($stream, 3);
            $this->assertSame(EventReadStatus::Success, $res->status());
            $this->assertTrue($this->testEvents[3]->eventId()->equals($res->event()->originalEvent()->eventId()));
        });
    }

    /** @test */
    public function after_setting_less_strict_truncatebefore_read_stream_forward_reads_more_events(): void
    {
        $this->execute(function (): void {
            $stream = 'after_setting_less_strict_truncatebefore_read_stream_forward_reads_more_events';

            $this->appendEvents($stream);
            $this->setStreamMetadata(
                $stream,
                ExpectedVersion::NoStream,
                StreamMetadata::create()->setTruncateBefore(2)
            );

            $res = $this->connection->readStreamEventsForward($stream, 0, 100, false, DefaultData::adminCredentials());
            $this->assertSame(SliceReadStatus::Success, $res->status());
            $this->assertCount(3, $res->events());

            $this->assertSameEventIds($res->events(), 2, true);

            $this->setStreamMetadata(
                $stream,
                0,
                StreamMetadata::create()->setTruncateBefore(1)
            );

            $res = $this->connection->readStreamEventsForward($stream, 0, 100, false, DefaultData::adminCredentials());
            $this->assertSame(SliceReadStatus::Success, $res->status());
            $this->assertCount(4, $res->events());

            $this->assertSameEventIds($res->events(), 1, true);
        });
    }

    /** @test */
    public function after_setting_more_strict_truncatebefore_read_stream_forward_reads_less_events(): void
    {
        $this->execute(function (): void {
            $stream = 'after_setting_more_strict_truncatebefore_read_stream_forward_reads_less_events';

            $this->appendEvents($stream);
            $this->setStreamMetadata(
                $stream,
                ExpectedVersion::NoStream,
                StreamMetadata::create()->setTruncateBefore(2)
            );

            $res = $this->connection->readStreamEventsForward($stream, 0, 100, false, DefaultData::adminCredentials());
            $this->assertSame(SliceReadStatus::Success, $res->status());
            $this->assertCount(3, $res->events());

            $this->assertSameEventIds($res->events(), 2, true);

            $this->setStreamMetadata(
                $stream,
                0,
                StreamMetadata::create()->setTruncateBefore(3)
            );

            $res = $this->connection->readStreamEventsForward($stream, 0, 100, false, DefaultData::adminCredentials());
            $this->assertSame(SliceReadStatus::Success, $res->status());
            $this->assertCount(2, $res->events());

            $this->assertSameEventIds($res->events(), 3, true);
        });
    }

    /** @test */
    public function less_strict_max_count_doesnt_change_anything_for_stream_forward_read(): void
    {
        $this->execute(function (): void {
            $stream = 'less_strict_max_count_doesnt_change_anything_for_stream_forward_read';

            $this->appendEvents($stream);
            $this->setStreamMetadata(
                $stream,
                ExpectedVersion::NoStream,
                StreamMetadata::create()->setTruncateBefore(2)
            );

            $res = $this->connection->readStreamEventsForward($stream, 0, 100, false, DefaultData::adminCredentials());
            $this->assertSame(SliceReadStatus::Success, $res->status());
            $this->assertCount(3, $res->events());

            $this->assertSameEventIds($res->events(), 2, true);

            $this->setStreamMetadata(
                $stream,
                0,
                StreamMetadata::create()->setTruncateBefore(2)->setMaxCount(4)
            );

            $res = $this->connection->readStreamEventsForward($stream, 0, 100, false, DefaultData::adminCredentials());
            $this->assertSame(SliceReadStatus::Success, $res->status());
            $this->assertCount(3, $res->events());

            $this->assertSameEventIds($res->events(), 2, true);
        });
    }

    /** @test */
    public function more_strict_max_count_gives_less_events_for_stream_forward_read(): void
    {
        $this->execute(function (): void {
            $stream = 'more_strict_max_count_gives_less_events_for_stream_forward_read';

            $this->appendEvents($stream);
            $this->setStreamMetadata(
                $stream,
                ExpectedVersion::NoStream,
                StreamMetadata::create()->setTruncateBefore(2)
            );

            $res = $this->connection->readStreamEventsForward($stream, 0, 100, false, DefaultData::adminCredentials());
            $this->assertSame(SliceReadStatus::Success, $res->status());
            $this->assertCount(3, $res->events());

            $this->assertSameEventIds($res->events(), 2, true);

            $this->setStreamMetadata(
                $stream,
                0,
                StreamMetadata::create()->setTruncateBefore(2)->setMaxCount(2)
            );

            $res = $this->connection->readStreamEventsForward($stream, 0, 100, false, DefaultData::adminCredentials());
            $this->assertSame(SliceReadStatus::Success, $res->status());
            $this->assertCount(2, $res->events());

            $this->assertSameEventIds($res->events(), 3, true);
        });
    }

    /** @test */
    public function after_setting_less_strict_truncatebefore_read_stream_backward_reads_more_events(): void
    {
        $this->execute(function (): void {
            $stream = 'after_setting_less_strict_truncatebefore_read_stream_backward_reads_more_events';

            $this->appendEvents($stream);
            $this->setStreamMetadata(
                $stream,
                ExpectedVersion::NoStream,
                StreamMetadata::create()->setTruncateBefore(2)
            );

            $res = $this->connection->readStreamEventsBackward($stream, -1, 100, false, DefaultData::adminCredentials());
            $this->assertSame(SliceReadStatus::Success, $res->status());
            $this->assertCount(3, $res->events());

            $this->assertSameEventIds($res->events(), 2, false);

            $this->setStreamMetadata(
                $stream,
                0,
                StreamMetadata::create()->setTruncateBefore(1)
            );

            $res = $this->connection->readStreamEventsBackward($stream, -1, 100, false, DefaultData::adminCredentials());
            $this->assertSame(SliceReadStatus::Success, $res->status());
            $this->assertCount(4, $res->events());

            $this->assertSameEventIds($res->events(), 1, false);
        });
    }

    /** @test */
    public function after_setting_more_strict_truncatebefore_read_stream_backward_reads_less_events(): void
    {
        $this->execute(function (): void {
            $stream = 'after_setting_more_strict_truncatebefore_read_stream_backward_reads_less_events';

            $this->appendEvents($stream);
            $this->setStreamMetadata(
                $stream,
                ExpectedVersion::NoStream,
                StreamMetadata::create()->setTruncateBefore(2)
            );

            $res = $this->connection->readStreamEventsBackward($stream, -1, 100, false, DefaultData::adminCredentials());
            $this->assertSame(SliceReadStatus::Success, $res->status());
            $this->assertCount(3, $res->events());

            $this->assertSameEventIds($res->events(), 2, false);

            $this->setStreamMetadata(
                $stream,
                0,
                StreamMetadata::create()->setTruncateBefore(3)
            );

            $res = $this->connection->readStreamEventsBackward($stream, -1, 100, false, DefaultData::adminCredentials());
            $this->assertSame(SliceReadStatus::Success, $res->status());
            $this->assertCount(2, $res->events());

            $this->assertSameEventIds($res->events(), 3, false);
        });
    }

    /** @test */
    public function less_strict_max_count_doesnt_change_anything_for_stream_backward_read(): void
    {
        $this->execute(function (): void {
            $stream = 'less_strict_max_count_doesnt_change_anything_for_stream_backward_read';

            $this->appendEvents($stream);
            $this->setStreamMetadata(
                $stream,
                ExpectedVersion::NoStream,
                StreamMetadata::create()->setTruncateBefore(2)
            );

            $res = $this->connection->readStreamEventsBackward($stream, -1, 100, false, DefaultData::adminCredentials());
            $this->assertSame(SliceReadStatus::Success, $res->status());
            $this->assertCount(3, $res->events());

            $this->assertSameEventIds($res->events(), 2, false);

            $this->setStreamMetadata(
                $stream,
                0,
                StreamMetadata::create()->setTruncateBefore(2)->setMaxCount(4)
            );

            $res = $this->connection->readStreamEventsBackward($stream, -1, 100, false, DefaultData::adminCredentials());
            $this->assertSame(SliceReadStatus::Success, $res->status());
            $this->assertCount(3, $res->events());

            $this->assertSameEventIds($res->events(), 2, false);
        });
    }

    /** @test */
    public function more_strict_max_count_gives_less_events_for_stream_backward_read(): void
    {
        $this->execute(function (): void {
            $stream = 'more_strict_max_count_gives_less_events_for_stream_backward_read';

            $this->appendEvents($stream);
            $this->setStreamMetadata(
                $stream,
                ExpectedVersion::NoStream,
                StreamMetadata::create()->setTruncateBefore(2)
            );

            $res = $this->connection->readStreamEventsBackward($stream, -1, 100, false, DefaultData::adminCredentials());
            $this->assertSame(SliceReadStatus::Success, $res->status());
            $this->assertCount(3, $res->events());

            $this->assertSameEventIds($res->events(), 2, false);

            $this->setStreamMetadata(
                $stream,
                0,
                StreamMetadata::create()->setTruncateBefore(2)->setMaxCount(2)
            );

            $res = $this->connection->readStreamEventsBackward($stream, -1, 100, false, DefaultData::adminCredentials());
            $this->assertSame(SliceReadStatus::Success, $res->status());
            $this->assertCount(2, $res->events());

            $this->assertSameEventIds($res->events(), 3, false);
        });
    }
}
