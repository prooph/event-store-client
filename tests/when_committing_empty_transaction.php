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

use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\EventStoreConnection;
use Prooph\EventStoreClient\EventStoreTransaction;
use Prooph\EventStoreClient\Exception\WrongExpectedVersionException;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\SliceReadStatus;
use Prooph\EventStoreClient\StreamEventsSlice;
use Prooph\EventStoreClient\Util\Guid;
use Prooph\EventStoreClient\WriteResult;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;
use function Amp\call;
use function Amp\Promise\wait;

class when_committing_empty_transaction extends TestCase
{
    /** @var EventStoreConnection */
    private $connection;
    /** @var EventData */
    private $firstEvent;
    /** @var string */
    private $stream;

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

            $this->expectException(WrongExpectedVersionException::class);

            yield $this->connection->appendToStreamAsync(
                $this->stream,
                ExpectedVersion::NO_STREAM,
                [TestEvent::newTestEvent()]
            );
        }));
    }
}
