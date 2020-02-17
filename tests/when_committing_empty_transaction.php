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
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\Async\EventStoreTransaction;
use Prooph\EventStore\EventData;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\Util\Guid;
use Prooph\EventStore\WriteResult;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;

class when_committing_empty_transaction extends TestCase
{
    private EventStoreConnection $connection;
    private EventData $firstEvent;
    private string $stream;

    protected function setUp(): void
    {
        $this->firstEvent = TestEvent::newTestEvent();
        $this->connection = TestConnection::create();
        $this->stream = Guid::generateAsHex();
    }

    private function bootstrap(): Generator
    {
        yield $this->connection->connectAsync();

        $result = yield $this->connection->appendToStreamAsync(
            $this->stream,
            ExpectedVersion::NO_STREAM,
            [$this->firstEvent, TestEvent::newTestEvent(), TestEvent::newTestEvent()]
        );
        \assert($result instanceof WriteResult);

        $this->assertSame(2, $result->nextExpectedVersion());

        $transaction = yield $this->connection->startTransactionAsync(
            $this->stream,
            2
        );
        \assert($transaction instanceof EventStoreTransaction);

        $result = yield $transaction->commitAsync();
        \assert($result instanceof WriteResult);

        $this->assertSame(2, $result->nextExpectedVersion());
    }

    protected function tearDown(): void
    {
        $this->connection->close();
    }

    /**
     * @test
     * @throws Throwable
     */
    public function following_append_with_correct_expected_version_are_commited_correctly(): void
    {
        wait(call(function () {
            yield from $this->bootstrap();

            $result = yield $this->connection->appendToStreamAsync(
                $this->stream,
                2,
                TestEvent::newAmount(2)
            );
            \assert($result instanceof WriteResult);

            $this->assertSame(4, $result->nextExpectedVersion());

            $result = yield $this->connection->readStreamEventsForwardAsync(
                $this->stream,
                0,
                100,
                false
            );
            \assert($result instanceof StreamEventsSlice);

            $this->assertTrue(SliceReadStatus::success()->equals($result->status()));
            $this->assertCount(5, $result->events());

            for ($i = 0; $i < 5; $i++) {
                $this->assertSame($i, $result->events()[$i]->originalEventNumber());
            }
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function following_append_with_expected_version_any_are_commited_correctly(): void
    {
        wait(call(function () {
            yield from $this->bootstrap();

            $result = yield $this->connection->appendToStreamAsync(
                $this->stream,
                ExpectedVersion::ANY,
                TestEvent::newAmount(2)
            );
            \assert($result instanceof WriteResult);

            $this->assertSame(4, $result->nextExpectedVersion());

            $result = yield $this->connection->readStreamEventsForwardAsync(
                $this->stream,
                0,
                100,
                false
            );
            \assert($result instanceof StreamEventsSlice);

            $this->assertTrue(SliceReadStatus::success()->equals($result->status()));
            $this->assertCount(5, $result->events());

            for ($i = 0; $i < 5; $i++) {
                $this->assertSame($i, $result->events()[$i]->originalEventNumber());
            }
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function committing_first_event_with_expected_version_no_stream_is_idempotent(): void
    {
        wait(call(function () {
            yield from $this->bootstrap();

            $result = yield $this->connection->appendToStreamAsync(
                $this->stream,
                ExpectedVersion::NO_STREAM,
                [$this->firstEvent]
            );
            \assert($result instanceof WriteResult);

            $this->assertSame(0, $result->nextExpectedVersion());

            $result = yield $this->connection->readStreamEventsForwardAsync(
                $this->stream,
                0,
                100,
                false
            );
            \assert($result instanceof StreamEventsSlice);

            $this->assertTrue(SliceReadStatus::success()->equals($result->status()));
            $this->assertCount(3, $result->events());

            for ($i = 0; $i < 3; $i++) {
                $this->assertSame($i, $result->events()[$i]->originalEventNumber());
            }
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function trying_to_append_new_events_with_expected_version_no_stream_fails(): void
    {
        wait(call(function () {
            yield from $this->bootstrap();

            $this->expectException(WrongExpectedVersion::class);

            yield $this->connection->appendToStreamAsync(
                $this->stream,
                ExpectedVersion::NO_STREAM,
                [TestEvent::newTestEvent()]
            );
        }));
    }
}
