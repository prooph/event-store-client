<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2019 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\Delayed;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\AsyncEventStoreConnection;
use Prooph\EventStore\EventData;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\RawStreamMetadataResult;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\StreamMetadataResult;
use Prooph\EventStore\WriteResult;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;
use function Amp\call;
use function Amp\Promise\wait;

class soft_delete extends TestCase
{
    /** @var AsyncEventStoreConnection */
    private $conn;

    protected function setUpTestCase(): Generator
    {
        $this->conn = TestConnection::create(DefaultData::adminCredentials());
        yield $this->conn->connectAsync();
    }

    protected function tearDownTestCase(): void
    {
        $this->conn->close();
    }

    /** @throws Throwable */
    protected function execute(callable $callback): void
    {
        wait(call(function () use ($callback) {
            yield from $this->setUpTestCase();

            yield from $callback();

            $this->tearDownTestCase();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function soft_deleted_stream_returns_no_stream_and_no_events_on_read(): void
    {
        $this->execute(function () {
            $stream = 'soft_deleted_stream_returns_no_stream_and_no_events_on_read';

            $result = yield $this->conn->appendToStreamAsync(
                $stream,
                ExpectedVersion::NO_STREAM,
                TestEvent::newAmount(2)
            );
            \assert($result instanceof WriteResult);

            $this->assertSame(1, $result->nextExpectedVersion());

            yield $this->conn->deleteStreamAsync($stream, 1);

            $result = yield $this->conn->readStreamEventsForwardAsync(
                $stream,
                0,
                100,
                false
            );
            \assert($result instanceof StreamEventsSlice);

            $this->assertTrue(SliceReadStatus::streamNotFound()->equals($result->status()));
            $this->assertCount(0, $result->events());
            $this->assertSame(1, $result->lastEventNumber());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function soft_deleted_stream_allows_recreation_when_expver_any(): void
    {
        $this->execute(function () {
            $stream = 'soft_deleted_stream_allows_recreation_when_expver_any';

            yield $this->conn->appendToStreamAsync(
                $stream,
                ExpectedVersion::NO_STREAM,
                TestEvent::newAmount(2)
            );

            yield $this->conn->deleteStreamAsync($stream, 1);

            $events = TestEvent::newAmount(3);

            $result = yield $this->conn->appendToStreamAsync(
                $stream,
                ExpectedVersion::ANY,
                $events
            );
            \assert($result instanceof WriteResult);

            $this->assertSame(4, $result->nextExpectedVersion());

            yield new Delayed(100); // wait for server to update stream

            $result = yield $this->conn->readStreamEventsForwardAsync(
                $stream,
                0,
                100,
                false
            );
            \assert($result instanceof StreamEventsSlice);

            $this->assertTrue(SliceReadStatus::success()->equals($result->status()));
            $this->assertSame(4, $result->lastEventNumber());
            $this->assertCount(3, $result->events());

            $expectedIds = \array_map(
                function (EventData $eventData): string {
                    return $eventData->eventId()->toString();
                },
                $events
            );

            $actualIds = \array_map(
                function (ResolvedEvent $resolvedEvent): string {
                    return $resolvedEvent->originalEvent()->eventId()->toString();
                },
                $result->events()
            );

            $this->assertSame($expectedIds, $actualIds);

            $actualNumbers = \array_map(
                function (ResolvedEvent $resolvedEvent): int {
                    return $resolvedEvent->originalEvent()->eventNumber();
                },
                $result->events()
            );

            $this->assertSame([2, 3, 4], $actualNumbers);

            $meta = yield $this->conn->getStreamMetadataAsync($stream);
            \assert($meta instanceof StreamMetadataResult);

            $this->assertSame(2, $meta->streamMetadata()->truncateBefore());
            $this->assertSame(1, $meta->metastreamVersion());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function soft_deleted_stream_allows_recreation_when_expver_no_stream(): void
    {
        $this->execute(function () {
            $stream = 'soft_deleted_stream_allows_recreation_when_expver_no_stream';

            yield $this->conn->appendToStreamAsync(
                $stream,
                ExpectedVersion::NO_STREAM,
                TestEvent::newAmount(2)
            );

            yield $this->conn->deleteStreamAsync($stream, 1);

            $events = TestEvent::newAmount(3);

            $result = yield $this->conn->appendToStreamAsync(
                $stream,
                ExpectedVersion::NO_STREAM,
                $events
            );
            \assert($result instanceof WriteResult);

            $this->assertSame(4, $result->nextExpectedVersion());

            yield new Delayed(100); // wait for server to update stream

            $result = yield $this->conn->readStreamEventsForwardAsync(
                $stream,
                0,
                100,
                false
            );
            \assert($result instanceof StreamEventsSlice);

            $this->assertTrue(SliceReadStatus::success()->equals($result->status()));
            $this->assertSame(4, $result->lastEventNumber());
            $this->assertCount(3, $result->events());

            $expectedIds = \array_map(
                function (EventData $eventData): string {
                    return $eventData->eventId()->toString();
                },
                $events
            );

            $actualIds = \array_map(
                function (ResolvedEvent $resolvedEvent): string {
                    return $resolvedEvent->originalEvent()->eventId()->toString();
                },
                $result->events()
            );

            $this->assertSame($expectedIds, $actualIds);

            $actualNumbers = \array_map(
                function (ResolvedEvent $resolvedEvent): int {
                    return $resolvedEvent->originalEvent()->eventNumber();
                },
                $result->events()
            );

            $this->assertSame([2, 3, 4], $actualNumbers);

            $meta = yield $this->conn->getStreamMetadataAsync($stream);
            \assert($meta instanceof StreamMetadataResult);

            $this->assertSame(2, $meta->streamMetadata()->truncateBefore());
            $this->assertSame(1, $meta->metastreamVersion());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function soft_deleted_stream_allows_recreation_when_expver_is_exact(): void
    {
        $this->execute(function () {
            $stream = 'soft_deleted_stream_allows_recreation_when_expver_is_exact';

            yield $this->conn->appendToStreamAsync(
                $stream,
                ExpectedVersion::NO_STREAM,
                TestEvent::newAmount(2)
            );

            yield $this->conn->deleteStreamAsync($stream, 1);

            $events = TestEvent::newAmount(3);

            $result = yield $this->conn->appendToStreamAsync(
                $stream,
                1,
                $events
            );
            \assert($result instanceof WriteResult);

            $this->assertSame(4, $result->nextExpectedVersion());

            yield new Delayed(100); // wait for server to update stream

            $result = yield $this->conn->readStreamEventsForwardAsync(
                $stream,
                0,
                100,
                false
            );
            \assert($result instanceof StreamEventsSlice);

            $this->assertTrue(SliceReadStatus::success()->equals($result->status()));
            $this->assertSame(4, $result->lastEventNumber());
            $this->assertCount(3, $result->events());

            $expectedIds = \array_map(
                function (EventData $eventData): string {
                    return $eventData->eventId()->toString();
                },
                $events
            );

            $actualIds = \array_map(
                function (ResolvedEvent $resolvedEvent): string {
                    return $resolvedEvent->originalEvent()->eventId()->toString();
                },
                $result->events()
            );

            $this->assertSame($expectedIds, $actualIds);

            $actualNumbers = \array_map(
                function (ResolvedEvent $resolvedEvent): int {
                    return $resolvedEvent->originalEvent()->eventNumber();
                },
                $result->events()
            );

            $this->assertSame([2, 3, 4], $actualNumbers);

            $meta = yield $this->conn->getStreamMetadataAsync($stream);
            \assert($meta instanceof StreamMetadataResult);

            $this->assertSame(2, $meta->streamMetadata()->truncateBefore());
            $this->assertSame(1, $meta->metastreamVersion());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function soft_deleted_stream_when_recreated_preserves_metadata_except_truncatebefore(): void
    {
        $this->execute(function () {
            $stream = 'soft_deleted_stream_when_recreated_preserves_metadata_except_truncatebefore';

            $result = yield $this->conn->appendToStreamAsync(
                $stream,
                ExpectedVersion::NO_STREAM,
                TestEvent::newAmount(2)
            );
            \assert($result instanceof WriteResult);

            $this->assertSame(1, $result->nextExpectedVersion());

            $result = yield $this->conn->setStreamMetadataAsync(
                $stream,
                ExpectedVersion::NO_STREAM,
                StreamMetadata::create()
                    ->setTruncateBefore(\PHP_INT_MAX)
                    ->setMaxCount(100)
                    ->setDeleteRoles('some-role')
                    ->setCustomProperty('key1', true)
                    ->setCustomProperty('key2', 17)
                    ->setCustomProperty('key3', 'some value')
                    ->build()
            );
            \assert($result instanceof WriteResult);

            $this->assertSame(0, $result->nextExpectedVersion());

            $events = TestEvent::newAmount(3);

            $result = yield $this->conn->appendToStreamAsync(
                $stream,
                1,
                $events
            );
            \assert($result instanceof WriteResult);

            $this->assertSame(4, $result->nextExpectedVersion());

            yield new Delayed(100); // wait for server to update stream

            $result = yield $this->conn->readStreamEventsForwardAsync(
                $stream,
                0,
                100,
                false
            );
            \assert($result instanceof StreamEventsSlice);

            $this->assertTrue(SliceReadStatus::success()->equals($result->status()));
            $this->assertSame(4, $result->lastEventNumber());
            $this->assertCount(3, $result->events());

            $actualNumbers = \array_map(
                function (ResolvedEvent $resolvedEvent): int {
                    return $resolvedEvent->originalEvent()->eventNumber();
                },
                $result->events()
            );

            $this->assertSame([2, 3, 4], $actualNumbers);

            $meta = yield $this->conn->getStreamMetadataAsync($stream);
            \assert($meta instanceof StreamMetadataResult);

            $this->assertSame(1, $meta->metastreamVersion());
            $this->assertSame(2, $meta->streamMetadata()->truncateBefore());
            $this->assertSame(100, $meta->streamMetadata()->maxCount());
            $this->assertSame(['some-role'], $meta->streamMetadata()->acl()->deleteRoles());
            $this->assertTrue($meta->streamMetadata()->getValue('key1'));
            $this->assertSame(17, $meta->streamMetadata()->getValue('key2'));
            $this->assertSame('some value', $meta->streamMetadata()->getValue('key3'));
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function soft_deleted_stream_can_be_hard_deleted(): void
    {
        $this->execute(function () {
            $stream = 'soft_deleted_stream_can_be_deleted';

            $result = yield $this->conn->appendToStreamAsync(
                $stream,
                ExpectedVersion::NO_STREAM,
                TestEvent::newAmount(2)
            );
            \assert($result instanceof WriteResult);

            $this->assertSame(1, $result->nextExpectedVersion());

            yield $this->conn->deleteStreamAsync($stream, 1);
            yield $this->conn->deleteStreamAsync($stream, ExpectedVersion::ANY, true);

            $result = yield $this->conn->readStreamEventsForwardAsync(
                $stream,
                0,
                100,
                false
            );
            \assert($result instanceof StreamEventsSlice);

            $this->assertTrue(SliceReadStatus::streamDeleted()->equals($result->status()));

            $meta = yield $this->conn->getStreamMetadataAsync($stream);
            \assert($meta instanceof StreamMetadataResult);

            $this->assertTrue($meta->isStreamDeleted());

            $this->expectException(StreamDeleted::class);

            yield $this->conn->appendToStreamAsync(
                $stream,
                ExpectedVersion::ANY,
                TestEvent::newAmount(1)
            );
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function soft_deleted_stream_allows_recreation_only_for_first_write(): void
    {
        $this->execute(function () {
            $stream = 'soft_deleted_stream_allows_recreation_only_for_first_write';

            $result = yield $this->conn->appendToStreamAsync(
                $stream,
                ExpectedVersion::NO_STREAM,
                TestEvent::newAmount(2)
            );
            \assert($result instanceof WriteResult);

            $this->assertSame(1, $result->nextExpectedVersion());

            yield $this->conn->deleteStreamAsync($stream, 1);

            $result = yield $this->conn->appendToStreamAsync(
                $stream,
                ExpectedVersion::NO_STREAM,
                TestEvent::newAmount(3)
            );
            \assert($result instanceof WriteResult);

            $this->assertSame(4, $result->nextExpectedVersion());

            try {
                yield $this->conn->appendToStreamAsync(
                    $stream,
                    ExpectedVersion::NO_STREAM,
                    TestEvent::newAmount(1)
                );

                $this->fail('Should have thrown');
            } catch (Throwable $e) {
                $this->assertInstanceOf(WrongExpectedVersion::class, $e);
            }

            $result = yield $this->conn->readStreamEventsForwardAsync(
                $stream,
                0,
                100,
                false
            );
            \assert($result instanceof StreamEventsSlice);

            $this->assertTrue(SliceReadStatus::success()->equals($result->status()));
            $this->assertSame(4, $result->lastEventNumber());
            $this->assertCount(3, $result->events());

            $actualNumbers = \array_map(
                function (ResolvedEvent $resolvedEvent): int {
                    return $resolvedEvent->originalEvent()->eventNumber();
                },
                $result->events()
            );

            $this->assertSame([2, 3, 4], $actualNumbers);

            $meta = yield $this->conn->getStreamMetadataAsync($stream);
            \assert($meta instanceof StreamMetadataResult);

            $this->assertSame(2, $meta->streamMetadata()->truncateBefore());
            $this->assertSame(1, $meta->metastreamVersion());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function soft_deleted_stream_appends_both_concurrent_writes_when_expver_any(): void
    {
        $this->execute(function () {
            $stream = 'soft_deleted_stream_appends_both_concurrent_writes_when_expver_any';

            $result = yield $this->conn->appendToStreamAsync(
                $stream,
                ExpectedVersion::NO_STREAM,
                TestEvent::newAmount(2)
            );
            \assert($result instanceof WriteResult);

            $this->assertSame(1, $result->nextExpectedVersion());

            yield $this->conn->deleteStreamAsync($stream, 1);

            $result = yield $this->conn->appendToStreamAsync(
                $stream,
                ExpectedVersion::ANY,
                TestEvent::newAmount(3)
            );
            \assert($result instanceof WriteResult);

            $this->assertSame(4, $result->nextExpectedVersion());

            $result = yield $this->conn->appendToStreamAsync(
                $stream,
                ExpectedVersion::ANY,
                TestEvent::newAmount(2)
            );
            \assert($result instanceof WriteResult);

            $this->assertSame(6, $result->nextExpectedVersion());

            $result = yield $this->conn->readStreamEventsForwardAsync(
                $stream,
                0,
                100,
                false
            );
            \assert($result instanceof StreamEventsSlice);

            $this->assertTrue(SliceReadStatus::success()->equals($result->status()));
            $this->assertSame(6, $result->lastEventNumber());
            $this->assertCount(5, $result->events());

            $actualNumbers = \array_map(
                function (ResolvedEvent $resolvedEvent): int {
                    return $resolvedEvent->originalEvent()->eventNumber();
                },
                $result->events()
            );

            $this->assertSame([2, 3, 4, 5, 6], $actualNumbers);

            $meta = yield $this->conn->getStreamMetadataAsync($stream);
            \assert($meta instanceof StreamMetadataResult);

            $this->assertSame(2, $meta->streamMetadata()->truncateBefore());
            $this->assertSame(1, $meta->metastreamVersion());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function setting_json_metadata_on_empty_soft_deleted_stream_recreates_stream_not_overriding_metadata(): void
    {
        $this->execute(function () {
            $stream = 'setting_json_metadata_on_empty_soft_deleted_stream_recreates_stream_not_overriding_metadata';

            yield $this->conn->deleteStreamAsync($stream, ExpectedVersion::NO_STREAM, false);

            $result = yield $this->conn->setStreamMetadataAsync(
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
            \assert($result instanceof WriteResult);

            $this->assertSame(1, $result->nextExpectedVersion());

            yield new Delayed(100); // wait for server to update stream

            $result = yield $this->conn->readStreamEventsForwardAsync(
                $stream,
                0,
                100,
                false
            );
            \assert($result instanceof StreamEventsSlice);

            $this->assertTrue(SliceReadStatus::streamNotFound()->equals($result->status()));
            $this->assertSame(-1, $result->lastEventNumber());
            $this->assertCount(0, $result->events());

            $meta = yield $this->conn->getStreamMetadataAsync($stream);
            \assert($meta instanceof StreamMetadataResult);

            $this->assertSame(2, $meta->metastreamVersion());
            $this->assertSame(0, $meta->streamMetadata()->truncateBefore());
            $this->assertSame(100, $meta->streamMetadata()->maxCount());
            $this->assertSame(['some-role'], $meta->streamMetadata()->acl()->deleteRoles());
            $this->assertTrue($meta->streamMetadata()->getValue('key1'));
            $this->assertSame(17, $meta->streamMetadata()->getValue('key2'));
            $this->assertSame('some value', $meta->streamMetadata()->getValue('key3'));
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function setting_json_metadata_on_nonempty_soft_deleted_stream_recreates_stream_not_overriding_metadata(): void
    {
        $this->execute(function () {
            $stream = 'setting_json_metadata_on_nonempty_soft_deleted_stream_recreates_stream_not_overriding_metadata';

            $result = yield $this->conn->appendToStreamAsync(
                $stream,
                ExpectedVersion::NO_STREAM,
                TestEvent::newAmount(2)
            );
            \assert($result instanceof WriteResult);

            $this->assertSame(1, $result->nextExpectedVersion());

            yield $this->conn->deleteStreamAsync($stream, 1, false);

            $result = yield $this->conn->setStreamMetadataAsync(
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
            \assert($result instanceof WriteResult);

            $this->assertSame(1, $result->nextExpectedVersion());

            yield new Delayed(100); // wait for server to update stream

            $result = yield $this->conn->readStreamEventsForwardAsync(
                $stream,
                0,
                100,
                false
            );
            \assert($result instanceof StreamEventsSlice);

            $this->assertTrue(SliceReadStatus::success()->equals($result->status()));
            $this->assertSame(1, $result->lastEventNumber());
            $this->assertCount(0, $result->events());

            $meta = yield $this->conn->getStreamMetadataAsync($stream);
            \assert($meta instanceof StreamMetadataResult);

            $this->assertSame(2, $meta->metastreamVersion());
            $this->assertSame(2, $meta->streamMetadata()->truncateBefore());
            $this->assertSame(100, $meta->streamMetadata()->maxCount());
            $this->assertSame(['some-role'], $meta->streamMetadata()->acl()->deleteRoles());
            $this->assertTrue($meta->streamMetadata()->getValue('key1'));
            $this->assertSame(17, $meta->streamMetadata()->getValue('key2'));
            $this->assertSame('some value', $meta->streamMetadata()->getValue('key3'));
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function setting_nonjson_metadata_on_empty_soft_deleted_stream_recreates_stream_keeping_original_metadata(): void
    {
        $this->execute(function () {
            $stream = 'setting_nonjson_metadata_on_empty_soft_deleted_stream_recreates_stream_overriding_metadata';
            $metadata = \random_bytes(256);

            yield $this->conn->deleteStreamAsync($stream, ExpectedVersion::NO_STREAM, false);

            $result = yield $this->conn->setRawStreamMetadataAsync($stream, 0, $metadata);
            \assert($result instanceof WriteResult);

            $this->assertSame(1, $result->nextExpectedVersion());

            yield new Delayed(100); // wait for server to update stream

            $result = yield $this->conn->readStreamEventsForwardAsync($stream, 0, 100, false);
            \assert($result instanceof StreamEventsSlice);

            $this->assertTrue(SliceReadStatus::streamNotFound()->equals($result->status()));
            $this->assertSame(-1, $result->lastEventNumber());
            $this->assertCount(0, $result->events());

            $meta = yield $this->conn->getRawStreamMetadataAsync($stream);
            \assert($meta instanceof RawStreamMetadataResult);

            $this->assertSame(1, $meta->metastreamVersion());
            $this->assertSame($metadata, $meta->streamMetadata());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function setting_nonjson_metadata_on_nonempty_soft_deleted_stream_recreates_stream_keeping_original_metadata(): void
    {
        $this->execute(function () {
            $stream = 'setting_nonjson_metadata_on_nonempty_soft_deleted_stream_recreates_stream_overriding_metadata';
            $metadata = \random_bytes(256);

            $result = yield $this->conn->appendToStreamAsync(
                $stream,
                ExpectedVersion::NO_STREAM,
                TestEvent::newAmount(2)
            );
            \assert($result instanceof WriteResult);

            $this->assertSame(1, $result->nextExpectedVersion());

            yield $this->conn->deleteStreamAsync($stream, 1, false);

            $result = yield $this->conn->setRawStreamMetadataAsync(
                $stream,
                0,
                $metadata
            );
            \assert($result instanceof WriteResult);

            $this->assertSame(1, $result->nextExpectedVersion());

            yield new Delayed(100); // wait for server to update stream

            $result = yield $this->conn->readStreamEventsForwardAsync($stream, 0, 100, false);
            \assert($result instanceof StreamEventsSlice);

            $this->assertTrue(SliceReadStatus::success()->equals($result->status()));
            $this->assertSame(1, $result->lastEventNumber());
            $this->assertCount(2, $result->events());

            $meta = yield $this->conn->getRawStreamMetadataAsync($stream);
            \assert($meta instanceof RawStreamMetadataResult);

            $this->assertSame(1, $meta->metastreamVersion());
            $this->assertSame($metadata, $meta->streamMetadata());
        });
    }
}
