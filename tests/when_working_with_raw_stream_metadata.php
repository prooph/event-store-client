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
use function Amp\Promise\wait;
use Closure;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\RawStreamMetadataResult;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;

class when_working_with_raw_stream_metadata extends TestCase
{
    private string $stream;
    private EventStoreConnection $conn;

    /** @throws Throwable */
    private function execute(Closure $function): void
    {
        wait(call(function () use ($function): Generator {
            $this->stream = self::class . '\\' . $this->getName();
            $this->conn = TestConnection::create();
            yield $this->conn->connectAsync();

            yield from $function();

            $this->conn->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function setting_empty_metadata_works(): void
    {
        $this->execute(function () {
            yield $this->conn->setStreamMetadataAsync(
                $this->stream,
                ExpectedVersion::NO_STREAM
            );

            $meta = yield $this->conn->getRawStreamMetadataAsync($this->stream);
            \assert($meta instanceof RawStreamMetadataResult);
            $this->assertEquals($this->stream, $meta->stream());
            $this->assertFalse($meta->isStreamDeleted());
            $this->assertEquals(0, $meta->metastreamVersion());
            $this->assertEquals('', $meta->streamMetadata());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function setting_metadata_few_times_returns_last_metadata(): void
    {
        $this->execute(function () {
            yield $this->conn->setStreamMetadataAsync(
                $this->stream,
                ExpectedVersion::NO_STREAM
            );

            $meta = yield $this->conn->getRawStreamMetadataAsync($this->stream);
            \assert($meta instanceof RawStreamMetadataResult);
            $this->assertEquals($this->stream, $meta->stream());
            $this->assertFalse($meta->isStreamDeleted());
            $this->assertEquals(0, $meta->metastreamVersion());
            $this->assertEquals('', $meta->streamMetadata());

            yield $this->conn->setStreamMetadataAsync(
                $this->stream,
                0
            );

            $meta = yield $this->conn->getRawStreamMetadataAsync($this->stream);
            \assert($meta instanceof RawStreamMetadataResult);
            $this->assertEquals($this->stream, $meta->stream());
            $this->assertFalse($meta->isStreamDeleted());
            $this->assertEquals(1, $meta->metastreamVersion());
            $this->assertEquals('', $meta->streamMetadata());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function trying_to_set_metadata_with_wrong_expected_version_fails(): void
    {
        $this->execute(function () {
            $this->expectException(WrongExpectedVersion::class);

            yield $this->conn->setStreamMetadataAsync(
                $this->stream,
                5
            );
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function setting_metadata_with_expected_version_any_works(): void
    {
        $this->execute(function () {
            yield $this->conn->setStreamMetadataAsync(
                $this->stream,
                ExpectedVersion::ANY
            );

            $meta = yield $this->conn->getRawStreamMetadataAsync($this->stream);
            \assert($meta instanceof RawStreamMetadataResult);
            $this->assertEquals($this->stream, $meta->stream());
            $this->assertFalse($meta->isStreamDeleted());
            $this->assertEquals(0, $meta->metastreamVersion());
            $this->assertEquals('', $meta->streamMetadata());

            yield $this->conn->setStreamMetadataAsync(
                $this->stream,
                ExpectedVersion::ANY
            );

            $meta = yield $this->conn->getRawStreamMetadataAsync($this->stream);
            \assert($meta instanceof RawStreamMetadataResult);
            $this->assertEquals($this->stream, $meta->stream());
            $this->assertFalse($meta->isStreamDeleted());
            $this->assertEquals(1, $meta->metastreamVersion());
            $this->assertEquals('', $meta->streamMetadata());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function setting_metadata_for_not_existing_stream_works(): void
    {
        $this->execute(function () {
            yield $this->conn->setStreamMetadataAsync(
                $this->stream,
                ExpectedVersion::NO_STREAM
            );

            $meta = yield $this->conn->getRawStreamMetadataAsync($this->stream);
            \assert($meta instanceof RawStreamMetadataResult);
            $this->assertEquals($this->stream, $meta->stream());
            $this->assertFalse($meta->isStreamDeleted());
            $this->assertEquals(0, $meta->metastreamVersion());
            $this->assertEquals('', $meta->streamMetadata());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function setting_metadata_for_existing_stream_works(): void
    {
        $this->execute(function () {
            yield $this->conn->appendToStreamAsync(
                $this->stream,
                ExpectedVersion::NO_STREAM,
                TestEvent::newAmount(2)
            );

            yield $this->conn->setStreamMetadataAsync(
                $this->stream,
                ExpectedVersion::NO_STREAM
            );

            $meta = yield $this->conn->getRawStreamMetadataAsync($this->stream);
            \assert($meta instanceof RawStreamMetadataResult);
            $this->assertEquals($this->stream, $meta->stream());
            $this->assertFalse($meta->isStreamDeleted());
            $this->assertEquals(0, $meta->metastreamVersion());
            $this->assertEquals('', $meta->streamMetadata());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function setting_metadata_for_deleted_stream_throws_stream_deleted_exception(): void
    {
        $this->execute(function () {
            yield $this->conn->deleteStreamAsync(
                $this->stream,
                ExpectedVersion::NO_STREAM,
                true
            );

            $this->expectException(StreamDeleted::class);

            yield $this->conn->setStreamMetadataAsync(
                $this->stream,
                ExpectedVersion::NO_STREAM
            );
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function getting_metadata_for_nonexisting_stream_returns_empty_string(): void
    {
        $this->execute(function () {
            $meta = yield $this->conn->getRawStreamMetadataAsync($this->stream);
            \assert($meta instanceof RawStreamMetadataResult);
            $this->assertEquals($this->stream, $meta->stream());
            $this->assertFalse($meta->isStreamDeleted());
            $this->assertEquals(-1, $meta->metastreamVersion());
            $this->assertEquals('', $meta->streamMetadata());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function getting_metadata_for_deleted_stream_returns_empty_string_and_signals_stream_deletion(): void
    {
        $this->execute(function () {
            yield $this->conn->setStreamMetadataAsync($this->stream, ExpectedVersion::NO_STREAM);

            yield $this->conn->deleteStreamAsync($this->stream, ExpectedVersion::NO_STREAM, true);

            $meta = yield $this->conn->getRawStreamMetadataAsync($this->stream);
            \assert($meta instanceof RawStreamMetadataResult);
            $this->assertEquals($this->stream, $meta->stream());
            $this->assertTrue($meta->isStreamDeleted());
            $this->assertEquals(EventNumber::DELETED_STREAM, $meta->metastreamVersion());
            $this->assertEquals('', $meta->streamMetadata());
        });
    }
}
