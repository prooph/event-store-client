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

use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\StreamMetadata;
use ProophTest\EventStoreClient\Helper\TestEvent;

class when_working_with_stream_metadata_as_structured_info extends EventStoreConnectionTestCase
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
            ExpectedVersion::Any,
            new StreamMetadata()
        );

        $meta = $this->connection->getRawStreamMetadata($this->stream);

        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(0, $meta->metastreamVersion());
        $this->assertSame('{}', $meta->streamMetadata());
    }

    /** @test */
    public function setting_metadata_few_times_returns_last_metadata_info(): void
    {
        $metadata = new StreamMetadata(17, 0xDEADBEEF, 10, 0xABACABA);

        $this->connection->setStreamMetadata(
            $this->stream,
            ExpectedVersion::Any,
            $metadata
        );

        $meta = $this->connection->getStreamMetadata($this->stream);

        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(0, $meta->metastreamVersion());
        $this->assertEmpty($meta->streamMetadata()->customMetadata());
        $this->assertSame($metadata->maxCount(), $meta->streamMetadata()->maxCount());
        $this->assertSame($metadata->maxAge(), $meta->streamMetadata()->maxAge());
        $this->assertSame($metadata->truncateBefore(), $meta->streamMetadata()->truncateBefore());
        $this->assertSame($metadata->cacheControl(), $meta->streamMetadata()->cacheControl());

        $metadata = new StreamMetadata(37, 0xBEEFDEAD, 24, 0xDABACABAD);

        $this->connection->setStreamMetadata(
            $this->stream,
            0,
            $metadata
        );

        $meta = $this->connection->getStreamMetadata($this->stream);

        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(1, $meta->metastreamVersion());
        $this->assertSame($metadata->maxCount(), $meta->streamMetadata()->maxCount());
        $this->assertSame($metadata->maxAge(), $meta->streamMetadata()->maxAge());
        $this->assertSame($metadata->truncateBefore(), $meta->streamMetadata()->truncateBefore());
        $this->assertSame($metadata->cacheControl(), $meta->streamMetadata()->cacheControl());

        $this->expectException(RuntimeException::class);
        $meta->streamMetadata()->getValue('unknown');
    }

    /** @test */
    public function trying_to_set_metadata_with_wrong_expected_version_fails(): void
    {
        $this->expectException(WrongExpectedVersion::class);

        $this->connection->setStreamMetadata(
            $this->stream,
            2,
            new StreamMetadata()
        );
    }

    /** @test */
    public function setting_metadata_with_expected_version_any_works(): void
    {
        $metadata = new StreamMetadata(17, 0xDEADBEEF, 10, 0xABACABA);

        $this->connection->setStreamMetadata(
            $this->stream,
            ExpectedVersion::Any,
            $metadata
        );

        $meta = $this->connection->getStreamMetadata($this->stream);

        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(0, $meta->metastreamVersion());
        $this->assertSame($metadata->maxCount(), $meta->streamMetadata()->maxCount());
        $this->assertSame($metadata->maxAge(), $meta->streamMetadata()->maxAge());
        $this->assertSame($metadata->truncateBefore(), $meta->streamMetadata()->truncateBefore());
        $this->assertSame($metadata->cacheControl(), $meta->streamMetadata()->cacheControl());

        $metadata = new StreamMetadata(37, 0xBEEFDEAD, 24, 0xDABACABAD);

        $this->connection->setStreamMetadata(
            $this->stream,
            ExpectedVersion::Any,
            $metadata
        );

        $meta = $this->connection->getStreamMetadata($this->stream);

        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(1, $meta->metastreamVersion());
        $this->assertSame($metadata->maxCount(), $meta->streamMetadata()->maxCount());
        $this->assertSame($metadata->maxAge(), $meta->streamMetadata()->maxAge());
        $this->assertSame($metadata->truncateBefore(), $meta->streamMetadata()->truncateBefore());
        $this->assertSame($metadata->cacheControl(), $meta->streamMetadata()->cacheControl());
    }

    /** @test */
    public function setting_metadata_for_not_existing_stream_works(): void
    {
        $metadata = new StreamMetadata(17, 0xDEADBEEF, 10, 0xABACABA);

        $this->connection->setStreamMetadata(
            $this->stream,
            ExpectedVersion::Any,
            $metadata
        );

        $meta = $this->connection->getStreamMetadata($this->stream);

        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(0, $meta->metastreamVersion());
        $this->assertSame($metadata->maxCount(), $meta->streamMetadata()->maxCount());
        $this->assertSame($metadata->maxAge(), $meta->streamMetadata()->maxAge());
        $this->assertSame($metadata->truncateBefore(), $meta->streamMetadata()->truncateBefore());
        $this->assertSame($metadata->cacheControl(), $meta->streamMetadata()->cacheControl());
    }

    /** @test */
    public function setting_metadata_for_existing_stream_works(): void
    {
        $this->connection->appendToStream(
            $this->stream,
            ExpectedVersion::NoStream,
            TestEvent::newAmount(2)
        );

        $metadata = new StreamMetadata(17, 0xDEADBEEF, 10, 0xABACABA);

        $this->connection->setStreamMetadata(
            $this->stream,
            ExpectedVersion::Any,
            $metadata
        );

        $meta = $this->connection->getStreamMetadata($this->stream);

        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(0, $meta->metastreamVersion());
        $this->assertSame($metadata->maxCount(), $meta->streamMetadata()->maxCount());
        $this->assertSame($metadata->maxAge(), $meta->streamMetadata()->maxAge());
        $this->assertSame($metadata->truncateBefore(), $meta->streamMetadata()->truncateBefore());
        $this->assertSame($metadata->cacheControl(), $meta->streamMetadata()->cacheControl());
    }

    /** @test */
    public function getting_metadata_for_nonexisting_stream_returns_empty_stream_metadata(): void
    {
        $meta = $this->connection->getStreamMetadata($this->stream);

        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(-1, $meta->metastreamVersion());
        $this->assertNull($meta->streamMetadata()->maxCount());
        $this->assertNull($meta->streamMetadata()->maxAge());
        $this->assertNull($meta->streamMetadata()->truncateBefore());
        $this->assertNull($meta->streamMetadata()->cacheControl());
    }

    /** @test */
    public function getting_metadata_for_deleted_stream_returns_empty_stream_metadata_and_signals_stream_deletion(): void
    {
        $metadata = new StreamMetadata(17, 0xDEADBEEF, 10, 0xABACABA);

        $this->connection->setStreamMetadata(
            $this->stream,
            ExpectedVersion::NoStream,
            $metadata
        );

        $this->connection->deleteStream(
            $this->stream,
            ExpectedVersion::NoStream,
            true
        );

        $meta = $this->connection->getStreamMetadata($this->stream);

        $this->assertSame($this->stream, $meta->stream());
        $this->assertTrue($meta->isStreamDeleted());
        $this->assertSame(EventNumber::DeleteStream, $meta->metastreamVersion());
        $this->assertNull($meta->streamMetadata()->maxCount());
        $this->assertNull($meta->streamMetadata()->maxAge());
        $this->assertNull($meta->streamMetadata()->truncateBefore());
        $this->assertNull($meta->streamMetadata()->cacheControl());
        $this->assertNull($meta->streamMetadata()->acl());
    }

    /** @test */
    public function setting_correctly_formatted_metadata_as_raw_allows_to_read_it_as_structured_metadata(): void
    {
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

        $this->connection->setRawStreamMetadata(
            $this->stream,
            ExpectedVersion::NoStream,
            $metadata
        );

        $meta = $this->connection->getStreamMetadata($this->stream);

        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(0, $meta->metastreamVersion());
        $this->assertSame(17, $meta->streamMetadata()->maxCount());
        $this->assertSame(123321, $meta->streamMetadata()->maxAge());
        $this->assertSame(23, $meta->streamMetadata()->truncateBefore());
        $this->assertSame(7654321, $meta->streamMetadata()->cacheControl());

        $this->assertNotNull($meta->streamMetadata()->acl());
        $this->assertSame('readRole', $meta->streamMetadata()->acl()->readRoles()[0]);
        $this->assertSame('writeRole', $meta->streamMetadata()->acl()->writeRoles()[0]);
        $this->assertSame('deleteRole', $meta->streamMetadata()->acl()->deleteRoles()[0]);

        // meta role removed to allow reading
        // $this->assertSame('metaReadRole', $meta->streamMetadata()->acl()->metaReadRoles()[0]);
        $this->assertSame('metaWriteRole', $meta->streamMetadata()->acl()->metaWriteRoles()[0]);

        $this->assertSame('a string', $meta->streamMetadata()->getValue('customString'));
        $this->assertSame(-179, $meta->streamMetadata()->getValue('customInt'));
        $this->assertSame(1.7, $meta->streamMetadata()->getValue('customDouble'));
        $this->assertSame(123123123123123123, $meta->streamMetadata()->getValue('customLong'));
        $this->assertTrue($meta->streamMetadata()->getValue('customBool'));
        $this->assertNull($meta->streamMetadata()->getValue('customNullable'));
        $this->assertSame(['subProperty' => 999], $meta->streamMetadata()->getValue('customRawJson'));
    }

    /** @test */
    public function setting_structured_metadata_with_custom_properties_returns_them_untouched(): void
    {
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

        $this->connection->setStreamMetadata(
            $this->stream,
            ExpectedVersion::NoStream,
            $metadata
        );

        $meta = $this->connection->getStreamMetadata($this->stream);

        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(0, $meta->metastreamVersion());
        $this->assertSame(17, $meta->streamMetadata()->maxCount());
        $this->assertSame(123321, $meta->streamMetadata()->maxAge());
        $this->assertSame(23, $meta->streamMetadata()->truncateBefore());
        $this->assertSame(7654321, $meta->streamMetadata()->cacheControl());

        $this->assertNotNull($meta->streamMetadata()->acl());
        $this->assertSame('readRole', $meta->streamMetadata()->acl()->readRoles()[0]);
        $this->assertSame('writeRole', $meta->streamMetadata()->acl()->writeRoles()[0]);
        $this->assertSame('deleteRole', $meta->streamMetadata()->acl()->deleteRoles()[0]);

        // meta role removed to allow reading
        // $this->assertSame('metaReadRole', $meta->streamMetadata()->acl()->metaReadRoles()[0]);
        $this->assertSame('metaWriteRole', $meta->streamMetadata()->acl()->metaWriteRoles()[0]);

        $this->assertSame('a string', $meta->streamMetadata()->getValue('customString'));
        $this->assertSame(-179, $meta->streamMetadata()->getValue('customInt'));
        $this->assertSame(1.7, $meta->streamMetadata()->getValue('customDouble'));
        $this->assertSame(123123123123123123, $meta->streamMetadata()->getValue('customLong'));
        $this->assertTrue($meta->streamMetadata()->getValue('customBool'));
        $this->assertNull($meta->streamMetadata()->getValue('customNullable'));
        $this->assertSame('{"subProperty": 999}', $meta->streamMetadata()->getValue('customRawJson'));
    }

    /** @test */
    public function setting_structured_metadata_with_multiple_roles_can_be_read_back(): void
    {
        $metadata = StreamMetadata::create()
            ->setReadRoles('r1', 'r2', 'r3')
            ->setWriteRoles('w1', 'w2')
            ->setDeleteRoles('d1', 'd2', 'd3', 'd4')
            ->setMetadataWriteRoles('mw1', 'mw2')
            ->build();

        $this->connection->setStreamMetadata(
            $this->stream,
            ExpectedVersion::NoStream,
            $metadata
        );

        $meta = $this->connection->getStreamMetadata($this->stream);

        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(0, $meta->metastreamVersion());
        $this->assertNotNull($meta->streamMetadata()->acl());

        $this->assertSame(['r1', 'r2', 'r3'], $meta->streamMetadata()->acl()->readRoles());
        $this->assertSame(['w1', 'w2'], $meta->streamMetadata()->acl()->writeRoles());
        $this->assertSame(['d1', 'd2', 'd3', 'd4'], $meta->streamMetadata()->acl()->deleteRoles());
        $this->assertSame(['mw1', 'mw2'], $meta->streamMetadata()->acl()->metaWriteRoles());
    }

    /** @test */
    public function setting_correct_metadata_with_multiple_roles_in_acl_allows_to_read_it_as_structured_metadata(): void
    {
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

        $this->connection->setRawStreamMetadata(
            $this->stream,
            ExpectedVersion::NoStream,
            $metadata
        );

        $meta = $this->connection->getStreamMetadata($this->stream);

        $this->assertSame($this->stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(0, $meta->metastreamVersion());
        $this->assertNotNull($meta->streamMetadata()->acl());

        $this->assertSame(['r1', 'r2', 'r3'], $meta->streamMetadata()->acl()->readRoles());
        $this->assertSame(['w1', 'w2'], $meta->streamMetadata()->acl()->writeRoles());
        $this->assertSame(['d1', 'd2', 'd3', 'd4'], $meta->streamMetadata()->acl()->deleteRoles());
        $this->assertSame(['mw1', 'mw2'], $meta->streamMetadata()->acl()->metaWriteRoles());
    }
}
