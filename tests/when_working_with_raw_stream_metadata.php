<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2021 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2021 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Generator;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\RawStreamMetadataResult;
use ProophTest\EventStoreClient\Helper\TestEvent;

class when_working_with_raw_stream_metadata extends EventStoreConnectionTestCase
{
    private string $stream;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stream = self::class . '\\' . $this->getName();
    }

    /** @test */
    public function setting_empty_metadata_works(): Generator
    {
        yield $this->connection->setStreamMetadataAsync(
            $this->stream,
            ExpectedVersion::NO_STREAM
        );

        $meta = yield $this->connection->getRawStreamMetadataAsync($this->stream);
        \assert($meta instanceof RawStreamMetadataResult);
        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(0, $meta->metastreamVersion());
        $this->assertSame('', $meta->streamMetadata());
    }

    /** @test */
    public function setting_metadata_few_times_returns_last_metadata(): Generator
    {
        yield $this->connection->setStreamMetadataAsync(
            $this->stream,
            ExpectedVersion::NO_STREAM
        );

        $meta = yield $this->connection->getRawStreamMetadataAsync($this->stream);
        \assert($meta instanceof RawStreamMetadataResult);
        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(0, $meta->metastreamVersion());
        $this->assertSame('', $meta->streamMetadata());

        yield $this->connection->setStreamMetadataAsync(
            $this->stream,
            0
        );

        $meta = yield $this->connection->getRawStreamMetadataAsync($this->stream);
        \assert($meta instanceof RawStreamMetadataResult);
        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(1, $meta->metastreamVersion());
        $this->assertSame('', $meta->streamMetadata());
    }

    /** @test */
    public function trying_to_set_metadata_with_wrong_expected_version_fails(): Generator
    {
        $this->expectException(WrongExpectedVersion::class);

        yield $this->connection->setStreamMetadataAsync(
            $this->stream,
            5
        );
    }

    /** @test */
    public function setting_metadata_with_expected_version_any_works(): Generator
    {
        yield $this->connection->setStreamMetadataAsync(
            $this->stream,
            ExpectedVersion::ANY
        );

        $meta = yield $this->connection->getRawStreamMetadataAsync($this->stream);
        \assert($meta instanceof RawStreamMetadataResult);
        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(0, $meta->metastreamVersion());
        $this->assertSame('', $meta->streamMetadata());

        yield $this->connection->setStreamMetadataAsync(
            $this->stream,
            ExpectedVersion::ANY
        );

        $meta = yield $this->connection->getRawStreamMetadataAsync($this->stream);
        \assert($meta instanceof RawStreamMetadataResult);
        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(1, $meta->metastreamVersion());
        $this->assertSame('', $meta->streamMetadata());
    }

    /** @test */
    public function setting_metadata_for_not_existing_stream_works(): Generator
    {
        yield $this->connection->setStreamMetadataAsync(
            $this->stream,
            ExpectedVersion::NO_STREAM
        );

        $meta = yield $this->connection->getRawStreamMetadataAsync($this->stream);
        \assert($meta instanceof RawStreamMetadataResult);
        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(0, $meta->metastreamVersion());
        $this->assertSame('', $meta->streamMetadata());
    }

    /** @test */
    public function setting_metadata_for_existing_stream_works(): Generator
    {
        yield $this->connection->appendToStreamAsync(
            $this->stream,
            ExpectedVersion::NO_STREAM,
            TestEvent::newAmount(2)
        );

        yield $this->connection->setStreamMetadataAsync(
            $this->stream,
            ExpectedVersion::NO_STREAM
        );

        $meta = yield $this->connection->getRawStreamMetadataAsync($this->stream);
        \assert($meta instanceof RawStreamMetadataResult);
        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(0, $meta->metastreamVersion());
        $this->assertSame('', $meta->streamMetadata());
    }

    /** @test */
    public function setting_metadata_for_deleted_stream_throws_stream_deleted_exception(): Generator
    {
        yield $this->connection->deleteStreamAsync(
            $this->stream,
            ExpectedVersion::NO_STREAM,
            true
        );

        $this->expectException(StreamDeleted::class);

        yield $this->connection->setStreamMetadataAsync(
            $this->stream,
            ExpectedVersion::NO_STREAM
        );
    }

    /** @test */
    public function getting_metadata_for_nonexisting_stream_returns_empty_string(): Generator
    {
        $meta = yield $this->connection->getRawStreamMetadataAsync($this->stream);
        \assert($meta instanceof RawStreamMetadataResult);
        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(-1, $meta->metastreamVersion());
        $this->assertSame('', $meta->streamMetadata());
    }

    /** @test */
    public function getting_metadata_for_deleted_stream_returns_empty_string_and_signals_stream_deletion(): Generator
    {
        yield $this->connection->setStreamMetadataAsync($this->stream, ExpectedVersion::NO_STREAM);

        yield $this->connection->deleteStreamAsync($this->stream, ExpectedVersion::NO_STREAM, true);

        $meta = yield $this->connection->getRawStreamMetadataAsync($this->stream);
        \assert($meta instanceof RawStreamMetadataResult);
        $this->assertSame($this->stream, $meta->stream());
        $this->assertTrue($meta->isStreamDeleted());
        $this->assertSame(EventNumber::DELETED_STREAM, $meta->metastreamVersion());
        $this->assertSame('', $meta->streamMetadata());
    }
}
