<?php
/**
 * This file is part of the prooph/event-store-client.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\Loop;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\StreamEventsSlice;
use Prooph\EventStoreClient\WriteResult;
use ProophTest\EventStoreClient\Helper\Connection;

class AppentToStreamTest extends TestCase
{
    /** @test */
    public function it_cannot_append_to_stream_without_name(): void
    {
        Loop::run(function () {
            $connection = Connection::createAsync();
            $this->expectException(InvalidArgumentException::class);
            yield $connection->appendToStreamAsync('', ExpectedVersion::Any, []);
        });
    }

    /** @test */
    public function it_should_allow_appending_zero_events_to_stream_with_no_problems(): void
    {
        Loop::run(function () {
            $stream1 = 'should_allow_appending_zero_events_to_stream_with_no_problems1';
            $stream2 = 'should_allow_appending_zero_events_to_stream_with_no_problems2';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            /** @var WriteResult $result */
            $result = yield $connection->appendToStreamAsync($stream1, ExpectedVersion::Any, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream1, ExpectedVersion::NoStream, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream1, ExpectedVersion::Any, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream1, ExpectedVersion::NoStream, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            /** @var StreamEventsSlice $slice */
            $slice = yield $connection->readStreamEventsForwardAsync($stream1, 0, 2, false);
            $this->assertSame(0, \count($slice->events()));

            $result = yield $connection->appendToStreamAsync($stream2, ExpectedVersion::NoStream, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream2, ExpectedVersion::Any, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream2, ExpectedVersion::NoStream, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $result = yield $connection->appendToStreamAsync($stream2, ExpectedVersion::Any, []);
            $this->assertSame(-1, $result->nextExpectedVersion());

            $slice = yield $connection->readStreamEventsForwardAsync($stream2, 0, 2, false);
            $this->assertSame(0, \count($slice->events()));

            $connection->close();
        });
    }
}
