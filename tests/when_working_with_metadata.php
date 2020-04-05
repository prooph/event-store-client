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

use Amp\PHPUnit\AsyncTestCase;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\RawStreamMetadataResult;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;

class when_working_with_metadata extends AsyncTestCase
{
    /**
     * @test
     */
    public function when_getting_metadata_for_an_existing_stream_and_no_metadata_exists(): \Generator
    {
        $connection = TestConnection::create();
        yield $connection->connectAsync();

        $stream = 'when_getting_metadata_for_an_existing_stream_and_no_metadata_exists';

        yield $connection->appendToStreamAsync(
            $stream,
            ExpectedVersion::NO_STREAM,
            [TestEvent::newTestEvent()]
        );

        $meta = yield $connection->getRawStreamMetadataAsync($stream);
        \assert($meta instanceof RawStreamMetadataResult);

        $this->assertSame($stream, $meta->stream());
        $this->assertFalse($meta->isStreamDeleted());
        $this->assertSame(-1, $meta->metastreamVersion());
        $this->assertEmpty($meta->streamMetadata());
    }
}
