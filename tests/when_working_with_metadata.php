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

use Prooph\EventStore\ExpectedVersion;
use ProophTest\EventStoreClient\Helper\TestEvent;

class when_working_with_metadata extends EventStoreConnectionTestCase
{
    /** @test */
    public function when_getting_metadata_for_an_existing_stream_and_no_metadata_exists(): void
    {
        $stream = 'when_getting_metadata_for_an_existing_stream_and_no_metadata_exists';

        $this->connection->appendToStream(
            $stream,
            ExpectedVersion::NoStream,
            [TestEvent::newTestEvent()]
        );

        $meta = $this->connection->getRawStreamMetadata($stream);

        $this->assertSame($stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(-1, $meta->metastreamVersion());
        $this->assertEmpty($meta->streamMetadata());
    }
}
