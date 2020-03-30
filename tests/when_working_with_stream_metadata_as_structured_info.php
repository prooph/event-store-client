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
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Closure;
use Generator;
use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\RawStreamMetadataResult;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\StreamMetadataResult;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;

class when_working_with_stream_metadata_as_structured_info extends AsyncTestCase
{
    private string $stream;
    private EventStoreConnection $conn;

    private function execute(Closure $function): Promise
    {
        return call(function () use ($function): Generator {
            $this->stream = self::class . '\\' . $this->getName();
            $this->conn = TestConnection::create(DefaultData::adminCredentials());
            yield $this->conn->connectAsync();

            yield from $function();

            $this->conn->close();
        });
    }

    /**
     * @test
     */
    public function setting_empty_metadata_works(): Generator
    {
        yield $this->execute(function (): Generator {
            yield $this->conn->setStreamMetadataAsync(
                $this->stream,
                ExpectedVersion::ANY,
                new StreamMetadata()
            );

            $meta = yield $this->conn->getRawStreamMetadataAsync($this->stream);
            \assert($meta instanceof RawStreamMetadataResult);

            $this->assertEquals($this->stream, $meta->stream());
            $this->assertFalse($meta->isStreamDeleted());
            $this->assertEquals(0, $meta->metastreamVersion());
            $this->assertEquals('{}', $meta->streamMetadata());
        });
    }

    /**
     * @test
     */
    public function setting_metadata_few_times_returns_last_metadata_info(): Generator
    {
        yield $this->execute(function (): Generator {
            $metadata = new StreamMetadata(17, 0xDEADBEEF, 10, 0xABACABA);

            yield $this->conn->setStreamMetadataAsync(
                $this->stream,
                ExpectedVersion::ANY,
                $metadata
            );

            $meta = yield $this->conn->getStreamMetadataAsync($this->stream);
            \assert($meta instanceof StreamMetadataResult);

            $this->assertEquals($this->stream, $meta->stream());
            $this->assertFalse($meta->isStreamDeleted());
            $this->assertEquals(0, $meta->metastreamVersion());
            $this->assertEmpty($meta->streamMetadata()->customMetadata());
            $this->assertEquals($metadata->maxCount(), $meta->streamMetadata()->maxCount());
            $this->assertEquals($metadata->maxAge(), $meta->streamMetadata()->maxAge());
            $this->assertEquals($metadata->truncateBefore(), $meta->streamMetadata()->truncateBefore());
            $this->assertEquals($metadata->cacheControl(), $meta->streamMetadata()->cacheControl());

            $metadata = new StreamMetadata(37, 0xBEEFDEAD, 24, 0xDABACABAD);

            yield $this->conn->setStreamMetadataAsync(
                $this->stream,
                0,
                $metadata
            );

            $meta = yield $this->conn->getStreamMetadataAsync($this->stream);
            \assert($meta instanceof StreamMetadataResult);

            $this->assertEquals($this->stream, $meta->stream());
            $this->assertFalse($meta->isStreamDeleted());
            $this->assertEquals(1, $meta->metastreamVersion());
            $this->assertEquals($metadata->maxCount(), $meta->streamMetadata()->maxCount());
            $this->assertEquals($metadata->maxAge(), $meta->streamMetadata()->maxAge());
            $this->assertEquals($metadata->truncateBefore(), $meta->streamMetadata()->truncateBefore());
            $this->assertEquals($metadata->cacheControl(), $meta->streamMetadata()->cacheControl());

            $this->expectException(RuntimeException::class);
            $meta->streamMetadata()->getValue('unknown');
        });
    }

    /**
     * @test
     */
    public function trying_to_set_metadata_with_wrong_expected_version_fails(): Generator
    {
        yield $this->execute(function (): Generator {
            $this->expectException(WrongExpectedVersion::class);

            yield $this->conn->setStreamMetadataAsync(
                $this->stream,
                2,
                new StreamMetadata()
            );
        });
    }

    /**
     * @test
     */
    public function setting_metadata_with_expected_version_any_works(): Generator
    {
        yield $this->execute(function (): Generator {
            $metadata = new StreamMetadata(17, 0xDEADBEEF, 10, 0xABACABA);

            yield $this->conn->setStreamMetadataAsync(
                $this->stream,
                ExpectedVersion::ANY,
                $metadata
            );

            $meta = yield $this->conn->getStreamMetadataAsync($this->stream);
            \assert($meta instanceof StreamMetadataResult);

            $this->assertEquals($this->stream, $meta->stream());
            $this->assertFalse($meta->isStreamDeleted());
            $this->assertEquals(0, $meta->metastreamVersion());
            $this->assertEquals($metadata->maxCount(), $meta->streamMetadata()->maxCount());
            $this->assertEquals($metadata->maxAge(), $meta->streamMetadata()->maxAge());
            $this->assertEquals($metadata->truncateBefore(), $meta->streamMetadata()->truncateBefore());
            $this->assertEquals($metadata->cacheControl(), $meta->streamMetadata()->cacheControl());

            $metadata = new StreamMetadata(37, 0xBEEFDEAD, 24, 0xDABACABAD);

            yield $this->conn->setStreamMetadataAsync(
                $this->stream,
                ExpectedVersion::ANY,
                $metadata
            );

            $meta = yield $this->conn->getStreamMetadataAsync($this->stream);
            \assert($meta instanceof StreamMetadataResult);

            $this->assertEquals($this->stream, $meta->stream());
            $this->assertFalse($meta->isStreamDeleted());
            $this->assertEquals(1, $meta->metastreamVersion());
            $this->assertEquals($metadata->maxCount(), $meta->streamMetadata()->maxCount());
            $this->assertEquals($metadata->maxAge(), $meta->streamMetadata()->maxAge());
            $this->assertEquals($metadata->truncateBefore(), $meta->streamMetadata()->truncateBefore());
            $this->assertEquals($metadata->cacheControl(), $meta->streamMetadata()->cacheControl());
        });
    }

    /**
     * @test
     */
    public function setting_metadata_for_not_existing_stream_works(): Generator
    {
        yield $this->execute(function (): Generator {
            $metadata = new StreamMetadata(17, 0xDEADBEEF, 10, 0xABACABA);

            yield $this->conn->setStreamMetadataAsync(
                $this->stream,
                ExpectedVersion::ANY,
                $metadata
            );

            $meta = yield $this->conn->getStreamMetadataAsync($this->stream);
            \assert($meta instanceof StreamMetadataResult);

            $this->assertEquals($this->stream, $meta->stream());
            $this->assertFalse($meta->isStreamDeleted());
            $this->assertEquals(0, $meta->metastreamVersion());
            $this->assertEquals($metadata->maxCount(), $meta->streamMetadata()->maxCount());
            $this->assertEquals($metadata->maxAge(), $meta->streamMetadata()->maxAge());
            $this->assertEquals($metadata->truncateBefore(), $meta->streamMetadata()->truncateBefore());
            $this->assertEquals($metadata->cacheControl(), $meta->streamMetadata()->cacheControl());
        });
    }

    /**
     * @test
     */
    public function setting_metadata_for_existing_stream_works(): Generator
    {
        yield $this->execute(function (): Generator {
            yield $this->conn->appendToStreamAsync(
                $this->stream,
                ExpectedVersion::NO_STREAM,
                TestEvent::newAmount(2)
            );

            $metadata = new StreamMetadata(17, 0xDEADBEEF, 10, 0xABACABA);

            yield $this->conn->setStreamMetadataAsync(
                $this->stream,
                ExpectedVersion::ANY,
                $metadata
            );

            $meta = yield $this->conn->getStreamMetadataAsync($this->stream);
            \assert($meta instanceof StreamMetadataResult);

            $this->assertEquals($this->stream, $meta->stream());
            $this->assertFalse($meta->isStreamDeleted());
            $this->assertEquals(0, $meta->metastreamVersion());
            $this->assertEquals($metadata->maxCount(), $meta->streamMetadata()->maxCount());
            $this->assertEquals($metadata->maxAge(), $meta->streamMetadata()->maxAge());
            $this->assertEquals($metadata->truncateBefore(), $meta->streamMetadata()->truncateBefore());
            $this->assertEquals($metadata->cacheControl(), $meta->streamMetadata()->cacheControl());
        });
    }

    /**
     * @test
     */
    public function getting_metadata_for_nonexisting_stream_returns_empty_stream_metadata(): Generator
    {
        yield $this->execute(function (): Generator {
            $meta = yield $this->conn->getStreamMetadataAsync($this->stream);
            \assert($meta instanceof StreamMetadataResult);

            $this->assertEquals($this->stream, $meta->stream());
            $this->assertFalse($meta->isStreamDeleted());
            $this->assertEquals(-1, $meta->metastreamVersion());
            $this->assertNull($meta->streamMetadata()->maxCount());
            $this->assertNull($meta->streamMetadata()->maxAge());
            $this->assertNull($meta->streamMetadata()->truncateBefore());
            $this->assertNull($meta->streamMetadata()->cacheControl());
        });
    }

    /**
     * @test
     */
    public function getting_metadata_for_deleted_stream_returns_empty_stream_metadata_and_signals_stream_deletion(): Generator
    {
        yield $this->execute(function (): Generator {
            $metadata = new StreamMetadata(17, 0xDEADBEEF, 10, 0xABACABA);

            yield $this->conn->setStreamMetadataAsync(
                $this->stream,
                ExpectedVersion::NO_STREAM,
                $metadata
            );

            yield $this->conn->deleteStreamAsync(
                $this->stream,
                ExpectedVersion::NO_STREAM,
                true
            );

            $meta = yield $this->conn->getStreamMetadataAsync($this->stream);
            \assert($meta instanceof StreamMetadataResult);

            $this->assertEquals($this->stream, $meta->stream());
            $this->assertTrue($meta->isStreamDeleted());
            $this->assertEquals(EventNumber::DELETED_STREAM, $meta->metastreamVersion());
            $this->assertNull($meta->streamMetadata()->maxCount());
            $this->assertNull($meta->streamMetadata()->maxAge());
            $this->assertNull($meta->streamMetadata()->truncateBefore());
            $this->assertNull($meta->streamMetadata()->cacheControl());
            $this->assertNull($meta->streamMetadata()->acl());
        });
    }

    /**
     * @test
     */
    public function setting_correctly_formatted_metadata_as_raw_allows_to_read_it_as_structured_metadata(): Generator
    {
        yield $this->execute(function (): Generator {
            $metadata = <<<END
{
   "\$maxCount": 17,
   "\$maxAge": 123321,
   "\$tb": 23,
   "\$cacheControl": 7654321,
   "\$acl": {
       "\$r": ["readRole"],
       "\$w": ["writeRole"],
       "\$d": ["deleteRole"],
       "\$mw": ["metaWriteRole"]
   },
   "customString": "a string",
   "customInt": -179,
   "customDouble": 1.7,
   "customLong": 123123123123123123,
   "customBool": true,
   "customNullable": null,
   "customRawJson": {
       "subProperty": 999
   }
}
END;

            yield $this->conn->setRawStreamMetadataAsync(
                $this->stream,
                ExpectedVersion::NO_STREAM,
                $metadata
            );

            $meta = yield $this->conn->getStreamMetadataAsync($this->stream);
            \assert($meta instanceof StreamMetadataResult);

            $this->assertEquals($this->stream, $meta->stream());
            $this->assertFalse($meta->isStreamDeleted());
            $this->assertEquals(0, $meta->metastreamVersion());
            $this->assertEquals(17, $meta->streamMetadata()->maxCount());
            $this->assertEquals(123321, $meta->streamMetadata()->maxAge());
            $this->assertEquals(23, $meta->streamMetadata()->truncateBefore());
            $this->assertEquals(7654321, $meta->streamMetadata()->cacheControl());

            $this->assertNotNull($meta->streamMetadata()->acl());
            $this->assertEquals('readRole', $meta->streamMetadata()->acl()->readRoles()[0]);
            $this->assertEquals('writeRole', $meta->streamMetadata()->acl()->writeRoles()[0]);
            $this->assertEquals('deleteRole', $meta->streamMetadata()->acl()->deleteRoles()[0]);

            // meta role removed to allow reading
            // $this->assertEquals('metaReadRole', $meta->streamMetadata()->acl()->metaReadRoles()[0]);
            $this->assertEquals('metaWriteRole', $meta->streamMetadata()->acl()->metaWriteRoles()[0]);

            $this->assertEquals('a string', $meta->streamMetadata()->getValue('customString'));
            $this->assertEquals(-179, $meta->streamMetadata()->getValue('customInt'));
            $this->assertEquals(1.7, $meta->streamMetadata()->getValue('customDouble'));
            $this->assertEquals(123123123123123123, $meta->streamMetadata()->getValue('customLong'));
            $this->assertTrue($meta->streamMetadata()->getValue('customBool'));
            $this->assertNull($meta->streamMetadata()->getValue('customNullable'));
            $this->assertEquals(['subProperty' => 999], $meta->streamMetadata()->getValue('customRawJson'));
        });
    }

    /**
     * @test
     */
    public function setting_structured_metadata_with_custom_properties_returns_them_untouched(): Generator
    {
        yield $this->execute(function (): Generator {
            $metadata = StreamMetadata::create()
                ->setMaxCount(17)
                ->setMaxAge(123321)
                ->setTruncateBefore(23)
                ->setCacheControl(7654321)
                ->setReadRoles('readRole')
                ->setWriteRoles('writeRole')
                ->setDeleteRoles('deleteRole')
                //->setMetadataReadRoles("metaReadRole")
                ->setMetadataWriteRoles('metaWriteRole')
                ->setCustomProperty('customString', 'a string')
                ->setCustomProperty('customInt', -179)
                ->setCustomProperty('customDouble', 1.7)
                ->setCustomProperty('customLong', 123123123123123123)
                ->setCustomProperty('customBool', true)
                ->setCustomProperty('customNullable', null)
                ->setCustomProperty('customRawJson', '{"subProperty": 999}')
                ->build();

            yield $this->conn->setStreamMetadataAsync(
                $this->stream,
                ExpectedVersion::NO_STREAM,
                $metadata
            );

            $meta = yield $this->conn->getStreamMetadataAsync($this->stream);
            \assert($meta instanceof StreamMetadataResult);

            $this->assertEquals($this->stream, $meta->stream());
            $this->assertFalse($meta->isStreamDeleted());
            $this->assertEquals(0, $meta->metastreamVersion());
            $this->assertEquals(17, $meta->streamMetadata()->maxCount());
            $this->assertEquals(123321, $meta->streamMetadata()->maxAge());
            $this->assertEquals(23, $meta->streamMetadata()->truncateBefore());
            $this->assertEquals(7654321, $meta->streamMetadata()->cacheControl());

            $this->assertNotNull($meta->streamMetadata()->acl());
            $this->assertEquals('readRole', $meta->streamMetadata()->acl()->readRoles()[0]);
            $this->assertEquals('writeRole', $meta->streamMetadata()->acl()->writeRoles()[0]);
            $this->assertEquals('deleteRole', $meta->streamMetadata()->acl()->deleteRoles()[0]);

            // meta role removed to allow reading
            // $this->assertEquals('metaReadRole', $meta->streamMetadata()->acl()->metaReadRoles()[0]);
            $this->assertEquals('metaWriteRole', $meta->streamMetadata()->acl()->metaWriteRoles()[0]);

            $this->assertEquals('a string', $meta->streamMetadata()->getValue('customString'));
            $this->assertEquals(-179, $meta->streamMetadata()->getValue('customInt'));
            $this->assertEquals(1.7, $meta->streamMetadata()->getValue('customDouble'));
            $this->assertEquals(123123123123123123, $meta->streamMetadata()->getValue('customLong'));
            $this->assertTrue($meta->streamMetadata()->getValue('customBool'));
            $this->assertNull($meta->streamMetadata()->getValue('customNullable'));
            $this->assertEquals('{"subProperty": 999}', $meta->streamMetadata()->getValue('customRawJson'));
        });
    }

    /**
     * @test
     */
    public function setting_structured_metadata_with_multiple_roles_can_be_read_back(): Generator
    {
        yield $this->execute(function (): Generator {
            $metadata = StreamMetadata::create()
                ->setReadRoles('r1', 'r2', 'r3')
                ->setWriteRoles('w1', 'w2')
                ->setDeleteRoles('d1', 'd2', 'd3', 'd4')
                ->setMetadataWriteRoles('mw1', 'mw2')
                ->build();

            yield $this->conn->setStreamMetadataAsync(
                $this->stream,
                ExpectedVersion::NO_STREAM,
                $metadata
            );

            $meta = yield $this->conn->getStreamMetadataAsync($this->stream);
            \assert($meta instanceof StreamMetadataResult);

            $this->assertEquals($this->stream, $meta->stream());
            $this->assertFalse($meta->isStreamDeleted());
            $this->assertEquals(0, $meta->metastreamVersion());
            $this->assertNotNull($meta->streamMetadata()->acl());

            $this->assertEquals(['r1', 'r2', 'r3'], $meta->streamMetadata()->acl()->readRoles());
            $this->assertEquals(['w1', 'w2'], $meta->streamMetadata()->acl()->writeRoles());
            $this->assertEquals(['d1', 'd2', 'd3', 'd4'], $meta->streamMetadata()->acl()->deleteRoles());
            $this->assertEquals(['mw1', 'mw2'], $meta->streamMetadata()->acl()->metaWriteRoles());
        });
    }

    /**
     * @test
     */
    public function setting_correct_metadata_with_multiple_roles_in_acl_allows_to_read_it_as_structured_metadata(): Generator
    {
        yield $this->execute(function (): Generator {
            $metadata = <<<END
{
   "\$acl": {
       "\$r": ["r1", "r2", "r3"],
       "\$w": ["w1", "w2"],
       "\$d": ["d1", "d2", "d3", "d4"],
       "\$mw": ["mw1", "mw2"]
   }
}
END;

            yield $this->conn->setRawStreamMetadataAsync(
                $this->stream,
                ExpectedVersion::NO_STREAM,
                $metadata
            );

            $meta = yield $this->conn->getStreamMetadataAsync($this->stream);
            \assert($meta instanceof StreamMetadataResult);

            $this->assertEquals($this->stream, $meta->stream());
            $this->assertFalse($meta->isStreamDeleted());
            $this->assertEquals(0, $meta->metastreamVersion());
            $this->assertNotNull($meta->streamMetadata()->acl());

            $this->assertEquals(['r1', 'r2', 'r3'], $meta->streamMetadata()->acl()->readRoles());
            $this->assertEquals(['w1', 'w2'], $meta->streamMetadata()->acl()->writeRoles());
            $this->assertEquals(['d1', 'd2', 'd3', 'd4'], $meta->streamMetadata()->acl()->deleteRoles());
            $this->assertEquals(['mw1', 'mw2'], $meta->streamMetadata()->acl()->metaWriteRoles());
        });
    }
}
