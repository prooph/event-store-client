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

use function Amp\delay;
use Prooph\EventStore\EventData;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamMetadata;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;

class soft_delete extends EventStoreConnectionTestCase
{
    /** @test */
    public function soft_deleted_stream_returns_no_stream_and_no_events_on_read(): void
    {
        $stream = 'soft_deleted_stream_returns_no_stream_and_no_events_on_read';

        $result = $this->connection->appendToStream(
            $stream,
            ExpectedVersion::NoStream,
            TestEvent::newAmount(2)
        );

        $this->assertSame(1, $result->nextExpectedVersion());

        $this->connection->deleteStream($stream, 1);

        $result = $this->connection->readStreamEventsForward(
            $stream,
            0,
            100,
            false
        );

        $this->assertSame(SliceReadStatus::StreamNotFound, $result->status());
        $this->assertCount(0, $result->events());
        $this->assertSame(1, $result->lastEventNumber());
    }

    /** @test */
    public function soft_deleted_stream_allows_recreation_when_expver_any(): void
    {
        $stream = 'soft_deleted_stream_allows_recreation_when_expver_any';

        $this->connection->appendToStream(
            $stream,
            ExpectedVersion::NoStream,
            TestEvent::newAmount(2)
        );

        $this->connection->deleteStream($stream, 1);

        $events = TestEvent::newAmount(3);

        $result = $this->connection->appendToStream(
            $stream,
            ExpectedVersion::Any,
            $events
        );

        $this->assertSame(4, $result->nextExpectedVersion());

        delay(0.1); // wait for server to update stream

        $result = $this->connection->readStreamEventsForward(
            $stream,
            0,
            100,
            false
        );

        $this->assertSame(SliceReadStatus::Success, $result->status());
        $this->assertSame(4, $result->lastEventNumber());
        $this->assertCount(3, $result->events());

        $expectedIds = \array_map(
            fn (EventData $eventData): string => $eventData->eventId()->toString(),
            $events
        );

        $actualIds = \array_map(
            fn (ResolvedEvent $resolvedEvent): string => $resolvedEvent->originalEvent()->eventId()->toString(),
            $result->events()
        );

        $this->assertSame($expectedIds, $actualIds);

        $actualNumbers = \array_map(
            fn (ResolvedEvent $resolvedEvent): int => $resolvedEvent->originalEvent()->eventNumber(),
            $result->events()
        );

        $this->assertSame([2, 3, 4], $actualNumbers);

        $meta = $this->connection->getStreamMetadata($stream);

        $this->assertSame(2, $meta->streamMetadata()->truncateBefore());
        $this->assertSame(1, $meta->metastreamVersion());
    }

    /** @test */
    public function soft_deleted_stream_allows_recreation_when_expver_no_stream(): void
    {
        $stream = 'soft_deleted_stream_allows_recreation_when_expver_no_stream';

        $this->connection->appendToStream(
            $stream,
            ExpectedVersion::NoStream,
            TestEvent::newAmount(2)
        );

        $this->connection->deleteStream($stream, 1);

        $events = TestEvent::newAmount(3);

        $result = $this->connection->appendToStream(
            $stream,
            ExpectedVersion::NoStream,
            $events
        );

        $this->assertSame(4, $result->nextExpectedVersion());

        delay(0.1); // wait for server to update stream

        $result = $this->connection->readStreamEventsForward(
            $stream,
            0,
            100,
            false
        );

        $this->assertSame(SliceReadStatus::Success, $result->status());
        $this->assertSame(4, $result->lastEventNumber());
        $this->assertCount(3, $result->events());

        $expectedIds = \array_map(
            fn (EventData $eventData): string => $eventData->eventId()->toString(),
            $events
        );

        $actualIds = \array_map(
            fn (ResolvedEvent $resolvedEvent): string => $resolvedEvent->originalEvent()->eventId()->toString(),
            $result->events()
        );

        $this->assertSame($expectedIds, $actualIds);

        $actualNumbers = \array_map(
            fn (ResolvedEvent $resolvedEvent): int => $resolvedEvent->originalEvent()->eventNumber(),
            $result->events()
        );

        $this->assertSame([2, 3, 4], $actualNumbers);

        $meta = $this->connection->getStreamMetadata($stream);

        $this->assertSame(2, $meta->streamMetadata()->truncateBefore());
        $this->assertSame(1, $meta->metastreamVersion());
    }

    /** @test */
    public function soft_deleted_stream_allows_recreation_when_expver_is_exact(): void
    {
        $stream = 'soft_deleted_stream_allows_recreation_when_expver_is_exact';

        $this->connection->appendToStream(
            $stream,
            ExpectedVersion::NoStream,
            TestEvent::newAmount(2)
        );

        $this->connection->deleteStream($stream, 1);

        $events = TestEvent::newAmount(3);

        $result = $this->connection->appendToStream(
            $stream,
            1,
            $events
        );

        $this->assertSame(4, $result->nextExpectedVersion());

        delay(0.1); // wait for server to update stream

        $result = $this->connection->readStreamEventsForward(
            $stream,
            0,
            100,
            false
        );

        $this->assertSame(SliceReadStatus::Success, $result->status());
        $this->assertSame(4, $result->lastEventNumber());
        $this->assertCount(3, $result->events());

        $expectedIds = \array_map(
            fn (EventData $eventData): string => $eventData->eventId()->toString(),
            $events
        );

        $actualIds = \array_map(
            fn (ResolvedEvent $resolvedEvent): string => $resolvedEvent->originalEvent()->eventId()->toString(),
            $result->events()
        );

        $this->assertSame($expectedIds, $actualIds);

        $actualNumbers = \array_map(
            fn (ResolvedEvent $resolvedEvent): int => $resolvedEvent->originalEvent()->eventNumber(),
            $result->events()
        );

        $this->assertSame([2, 3, 4], $actualNumbers);

        $meta = $this->connection->getStreamMetadata($stream);

        $this->assertSame(2, $meta->streamMetadata()->truncateBefore());
        $this->assertSame(1, $meta->metastreamVersion());
    }

    /** @test */
    public function soft_deleted_stream_when_recreated_preserves_metadata_except_truncatebefore(): void
    {
        $stream = 'soft_deleted_stream_when_recreated_preserves_metadata_except_truncatebefore';

        $result = $this->connection->appendToStream(
            $stream,
            ExpectedVersion::NoStream,
            TestEvent::newAmount(2)
        );

        $this->assertSame(1, $result->nextExpectedVersion());

        $result = $this->connection->setStreamMetadata(
            $stream,
            ExpectedVersion::NoStream,
            StreamMetadata::create()
                ->setTruncateBefore(\PHP_INT_MAX)
                ->setMaxCount(100)
                ->setDeleteRoles('some-role')
                ->setCustomProperty('key1', true)
                ->setCustomProperty('key2', 17)
                ->setCustomProperty('key3', 'some value')
                ->build()
        );

        $this->assertSame(0, $result->nextExpectedVersion());

        $events = TestEvent::newAmount(3);

        $result = $this->connection->appendToStream(
            $stream,
            1,
            $events
        );

        $this->assertSame(4, $result->nextExpectedVersion());

        delay(0.1); // wait for server to update stream

        $result = $this->connection->readStreamEventsForward(
            $stream,
            0,
            100,
            false
        );

        $this->assertSame(SliceReadStatus::Success, $result->status());
        $this->assertSame(4, $result->lastEventNumber());
        $this->assertCount(3, $result->events());

        $actualNumbers = \array_map(
            fn (ResolvedEvent $resolvedEvent): int => $resolvedEvent->originalEvent()->eventNumber(),
            $result->events()
        );

        $this->assertSame([2, 3, 4], $actualNumbers);

        $meta = $this->connection->getStreamMetadata($stream);

        $this->assertSame(1, $meta->metastreamVersion());
        $this->assertSame(2, $meta->streamMetadata()->truncateBefore());
        $this->assertSame(100, $meta->streamMetadata()->maxCount());
        $this->assertSame(['some-role'], $meta->streamMetadata()->acl()->deleteRoles());
        $this->assertTrue($meta->streamMetadata()->getValue('key1'));
        $this->assertSame(17, $meta->streamMetadata()->getValue('key2'));
        $this->assertSame('some value', $meta->streamMetadata()->getValue('key3'));
    }

    /** @test */
    public function soft_deleted_stream_can_be_hard_deleted(): void
    {
        $stream = 'soft_deleted_stream_can_be_deleted';

        $result = $this->connection->appendToStream(
            $stream,
            ExpectedVersion::NoStream,
            TestEvent::newAmount(2)
        );

        $this->assertSame(1, $result->nextExpectedVersion());

        $this->connection->deleteStream($stream, 1);
        $this->connection->deleteStream($stream, ExpectedVersion::Any, true);

        $result = $this->connection->readStreamEventsForward(
            $stream,
            0,
            100,
            false
        );

        $this->assertSame(SliceReadStatus::StreamDeleted, $result->status());

        $meta = $this->connection->getStreamMetadata($stream);

        $this->assertTrue($meta->isStreamDeleted());

        $this->expectException(StreamDeleted::class);

        $this->connection->appendToStream(
            $stream,
            ExpectedVersion::Any,
            TestEvent::newAmount(1)
        );
    }

    /** @test */
    public function soft_deleted_stream_allows_recreation_only_for_first_write(): void
    {
        $stream = 'soft_deleted_stream_allows_recreation_only_for_first_write';

        $result = $this->connection->appendToStream(
            $stream,
            ExpectedVersion::NoStream,
            TestEvent::newAmount(2)
        );

        $this->assertSame(1, $result->nextExpectedVersion());

        $this->connection->deleteStream($stream, 1);

        $result = $this->connection->appendToStream(
            $stream,
            ExpectedVersion::NoStream,
            TestEvent::newAmount(3)
        );

        $this->assertSame(4, $result->nextExpectedVersion());

        try {
            $this->connection->appendToStream(
                $stream,
                ExpectedVersion::NoStream,
                TestEvent::newAmount(1)
            );

            $this->fail('Should have thrown');
        } catch (Throwable $e) {
            $this->assertInstanceOf(WrongExpectedVersion::class, $e);
        }

        $result = $this->connection->readStreamEventsForward(
            $stream,
            0,
            100,
            false
        );

        $this->assertSame(SliceReadStatus::Success, $result->status());
        $this->assertSame(4, $result->lastEventNumber());
        $this->assertCount(3, $result->events());

        $actualNumbers = \array_map(
            fn (ResolvedEvent $resolvedEvent): int => $resolvedEvent->originalEvent()->eventNumber(),
            $result->events()
        );

        $this->assertSame([2, 3, 4], $actualNumbers);

        $meta = $this->connection->getStreamMetadata($stream);

        $this->assertSame(2, $meta->streamMetadata()->truncateBefore());
        $this->assertSame(1, $meta->metastreamVersion());
    }

    /** @test */
    public function soft_deleted_stream_appends_both_concurrent_writes_when_expver_any(): void
    {
        $stream = 'soft_deleted_stream_appends_both_concurrent_writes_when_expver_any';

        $result = $this->connection->appendToStream(
            $stream,
            ExpectedVersion::NoStream,
            TestEvent::newAmount(2)
        );

        $this->assertSame(1, $result->nextExpectedVersion());

        $this->connection->deleteStream($stream, 1);

        $result = $this->connection->appendToStream(
            $stream,
            ExpectedVersion::Any,
            TestEvent::newAmount(3)
        );

        $this->assertSame(4, $result->nextExpectedVersion());

        $result = $this->connection->appendToStream(
            $stream,
            ExpectedVersion::Any,
            TestEvent::newAmount(2)
        );

        $this->assertSame(6, $result->nextExpectedVersion());

        $result = $this->connection->readStreamEventsForward(
            $stream,
            0,
            100,
            false
        );

        $this->assertSame(SliceReadStatus::Success, $result->status());
        $this->assertSame(6, $result->lastEventNumber());
        $this->assertCount(5, $result->events());

        $actualNumbers = \array_map(
            fn (ResolvedEvent $resolvedEvent): int => $resolvedEvent->originalEvent()->eventNumber(),
            $result->events()
        );

        $this->assertSame([2, 3, 4, 5, 6], $actualNumbers);

        $meta = $this->connection->getStreamMetadata($stream);

        $this->assertSame(2, $meta->streamMetadata()->truncateBefore());
        $this->assertSame(1, $meta->metastreamVersion());
    }

    /** @test */
    public function setting_json_metadata_on_empty_soft_deleted_stream_recreates_stream_not_overriding_metadata(): void
    {
        $stream = 'setting_json_metadata_on_empty_soft_deleted_stream_recreates_stream_not_overriding_metadata';

        $this->connection->deleteStream($stream, ExpectedVersion::NoStream, false);

        $result = $this->connection->setStreamMetadata(
            $stream,
            0,
            StreamMetadata::create()
                ->setTruncateBefore(\PHP_INT_MAX)
                ->setMaxCount(100)
                ->setDeleteRoles('some-role')
                ->setCustomProperty('key1', true)
                ->setCustomProperty('key2', 17)
                ->setCustomProperty('key3', 'some value')
                ->build()
        );

        $this->assertSame(1, $result->nextExpectedVersion());

        delay(0.1); // wait for server to update stream

        $result = $this->connection->readStreamEventsForward(
            $stream,
            0,
            100,
            false
        );

        $this->assertSame(SliceReadStatus::StreamNotFound, $result->status());
        $this->assertSame(-1, $result->lastEventNumber());
        $this->assertCount(0, $result->events());

        $meta = $this->connection->getStreamMetadata($stream);

        $this->assertSame(2, $meta->metastreamVersion());
        $this->assertSame(0, $meta->streamMetadata()->truncateBefore());
        $this->assertSame(100, $meta->streamMetadata()->maxCount());
        $this->assertSame(['some-role'], $meta->streamMetadata()->acl()->deleteRoles());
        $this->assertTrue($meta->streamMetadata()->getValue('key1'));
        $this->assertSame(17, $meta->streamMetadata()->getValue('key2'));
        $this->assertSame('some value', $meta->streamMetadata()->getValue('key3'));
    }

    /** @test */
    public function setting_json_metadata_on_nonempty_soft_deleted_stream_recreates_stream_not_overriding_metadata(): void
    {
        $stream = 'setting_json_metadata_on_nonempty_soft_deleted_stream_recreates_stream_not_overriding_metadata';

        $result = $this->connection->appendToStream(
            $stream,
            ExpectedVersion::NoStream,
            TestEvent::newAmount(2)
        );

        $this->assertSame(1, $result->nextExpectedVersion());

        $this->connection->deleteStream($stream, 1, false);

        $result = $this->connection->setStreamMetadata(
            $stream,
            0,
            StreamMetadata::create()
                ->setTruncateBefore(\PHP_INT_MAX)
                ->setMaxCount(100)
                ->setDeleteRoles('some-role')
                ->setCustomProperty('key1', true)
                ->setCustomProperty('key2', 17)
                ->setCustomProperty('key3', 'some value')
                ->build()
        );

        $this->assertSame(1, $result->nextExpectedVersion());

        delay(0.1); // wait for server to update stream

        $result = $this->connection->readStreamEventsForward(
            $stream,
            0,
            100,
            false
        );

        $this->assertSame(SliceReadStatus::Success, $result->status());
        $this->assertSame(1, $result->lastEventNumber());
        $this->assertCount(0, $result->events());

        $meta = $this->connection->getStreamMetadata($stream);

        $this->assertSame(2, $meta->metastreamVersion());
        $this->assertSame(2, $meta->streamMetadata()->truncateBefore());
        $this->assertSame(100, $meta->streamMetadata()->maxCount());
        $this->assertSame(['some-role'], $meta->streamMetadata()->acl()->deleteRoles());
        $this->assertTrue($meta->streamMetadata()->getValue('key1'));
        $this->assertSame(17, $meta->streamMetadata()->getValue('key2'));
        $this->assertSame('some value', $meta->streamMetadata()->getValue('key3'));
    }

    /** @test */
    public function setting_nonjson_metadata_on_empty_soft_deleted_stream_recreates_stream_keeping_original_metadata(): void
    {
        $stream = 'setting_nonjson_metadata_on_empty_soft_deleted_stream_recreates_stream_overriding_metadata';
        $metadata = \random_bytes(256);

        $this->connection->deleteStream($stream, ExpectedVersion::NoStream, false);

        $result = $this->connection->setRawStreamMetadata($stream, 0, $metadata);

        $this->assertSame(1, $result->nextExpectedVersion());

        delay(0.1); // wait for server to update stream

        $result = $this->connection->readStreamEventsForward($stream, 0, 100, false);

        $this->assertSame(SliceReadStatus::StreamNotFound, $result->status());
        $this->assertSame(-1, $result->lastEventNumber());
        $this->assertCount(0, $result->events());

        $meta = $this->connection->getRawStreamMetadata($stream);

        $this->assertSame(1, $meta->metastreamVersion());
        $this->assertSame($metadata, $meta->streamMetadata());
    }

    /** @test */
    public function setting_nonjson_metadata_on_nonempty_soft_deleted_stream_recreates_stream_keeping_original_metadata(): void
    {
        $stream = 'setting_nonjson_metadata_on_nonempty_soft_deleted_stream_recreates_stream_overriding_metadata';
        $metadata = \random_bytes(256);

        $result = $this->connection->appendToStream(
            $stream,
            ExpectedVersion::NoStream,
            TestEvent::newAmount(2)
        );

        $this->assertSame(1, $result->nextExpectedVersion());

        $this->connection->deleteStream($stream, 1, false);

        $result = $this->connection->setRawStreamMetadata(
            $stream,
            0,
            $metadata
        );

        $this->assertSame(1, $result->nextExpectedVersion());

        delay(0.1); // wait for server to update stream

        $result = $this->connection->readStreamEventsForward($stream, 0, 100, false);

        $this->assertSame(SliceReadStatus::Success, $result->status());
        $this->assertSame(1, $result->lastEventNumber());
        $this->assertCount(2, $result->events());

        $meta = $this->connection->getRawStreamMetadata($stream);

        $this->assertSame(1, $meta->metastreamVersion());
        $this->assertSame($metadata, $meta->streamMetadata());
    }
}
