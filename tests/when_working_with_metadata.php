<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\RawStreamMetadataResult;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;
use function Amp\call;
use function Amp\Promise\wait;

class when_working_with_metadata extends TestCase
{
    /**
     * @test
     * @throws Throwable
     */
    public function when_getting_metadata_for_an_existing_stream_and_no_metadata_exists(): void
    {
        wait(call(function () {
            $connection = TestConnection::createAsync();
            yield $connection->connectAsync();

            $stream = 'when_getting_metadata_for_an_existing_stream_and_no_metadata_exists';

            yield $connection->appendToStreamAsync(
                $stream,
                ExpectedVersion::EMPTY_STREAM,
                [TestEvent::newTestEvent()]
            );

            /** @var RawStreamMetadataResult $meta */
            $meta = yield $connection->getRawStreamMetadataAsync($stream);

            $this->assertSame($stream, $meta->stream());
            $this->assertFalse($meta->isStreamDeleted());
            $this->assertSame(-1, $meta->metastreamVersion());
            $this->assertEmpty($meta->streamMetadata());

            $connection->close();
        }));
    }
}
