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

use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
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
    public function setting_empty_metadata_works(): void
    {
        $this->connection->setStreamMetadata(
            $this->stream,
            ExpectedVersion::NoStream
        );

        $meta = $this->connection->getRawStreamMetadata($this->stream);
        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(0, $meta->metastreamVersion());
        $this->assertSame('', $meta->streamMetadata());
    }

    /** @test */
    public function setting_metadata_few_times_returns_last_metadata(): void
    {
        $this->connection->setStreamMetadata(
            $this->stream,
            ExpectedVersion::NoStream
        );

        $meta = $this->connection->getRawStreamMetadata($this->stream);
        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(0, $meta->metastreamVersion());
        $this->assertSame('', $meta->streamMetadata());

        $this->connection->setStreamMetadata(
            $this->stream,
            0
        );

        $meta = $this->connection->getRawStreamMetadata($this->stream);
        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(1, $meta->metastreamVersion());
        $this->assertSame('', $meta->streamMetadata());
    }

    /** @test */
    public function trying_to_set_metadata_with_wrong_expected_version_fails(): void
    {
        $this->expectException(WrongExpectedVersion::class);

        $this->connection->setStreamMetadata(
            $this->stream,
            5
        );
    }

    /** @test */
    public function setting_metadata_with_expected_version_any_works(): void
    {
        $this->connection->setStreamMetadata(
            $this->stream,
            ExpectedVersion::Any
        );

        $meta = $this->connection->getRawStreamMetadata($this->stream);
        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(0, $meta->metastreamVersion());
        $this->assertSame('', $meta->streamMetadata());

        $this->connection->setStreamMetadata(
            $this->stream,
            ExpectedVersion::Any
        );

        $meta = $this->connection->getRawStreamMetadata($this->stream);
        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(1, $meta->metastreamVersion());
        $this->assertSame('', $meta->streamMetadata());
    }

    /** @test */
    public function setting_metadata_for_not_existing_stream_works(): void
    {
        $this->connection->setStreamMetadata(
            $this->stream,
            ExpectedVersion::NoStream
        );

        $meta = $this->connection->getRawStreamMetadata($this->stream);
        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(0, $meta->metastreamVersion());
        $this->assertSame('', $meta->streamMetadata());
    }

    /** @test */
    public function setting_metadata_for_existing_stream_works(): void
    {
        $this->connection->appendToStream(
            $this->stream,
            ExpectedVersion::NoStream,
            TestEvent::newAmount(2)
        );

        $this->connection->setStreamMetadata(
            $this->stream,
            ExpectedVersion::NoStream
        );

        $meta = $this->connection->getRawStreamMetadata($this->stream);
        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(0, $meta->metastreamVersion());
        $this->assertSame('', $meta->streamMetadata());
    }

    /** @test */
    public function setting_metadata_for_deleted_stream_throws_stream_deleted_exception(): void
    {
        $this->connection->deleteStream(
            $this->stream,
            ExpectedVersion::NoStream,
            true
        );

        $this->expectException(StreamDeleted::class);

        $this->connection->setStreamMetadata(
            $this->stream,
            ExpectedVersion::NoStream
        );
    }

    /** @test */
    public function getting_metadata_for_nonexisting_stream_returns_empty_string(): void
    {
        $meta = $this->connection->getRawStreamMetadata($this->stream);
        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(-1, $meta->metastreamVersion());
        $this->assertSame('', $meta->streamMetadata());
    }

    /** @test */
    public function getting_metadata_for_deleted_stream_returns_empty_string_and_signals_stream_deletion(): void
    {
        $this->connection->setStreamMetadata($this->stream, ExpectedVersion::NoStream);

        $this->connection->deleteStream($this->stream, ExpectedVersion::NoStream, true);

        $meta = $this->connection->getRawStreamMetadata($this->stream);
        $this->assertSame($this->stream, $meta->stream());
        $this->assertTrue($meta->isStreamDeleted());
        $this->assertSame(EventNumber::DeleteStream, $meta->metastreamVersion());
        $this->assertSame('', $meta->streamMetadata());
    }
}
