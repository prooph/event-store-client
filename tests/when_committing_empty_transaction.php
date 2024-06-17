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

use Amp\PHPUnit\AsyncTestCase;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventStoreConnection;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\Util\Guid;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;

class when_committing_empty_transaction extends AsyncTestCase
{
    private EventStoreConnection $connection;

    private EventData $firstEvent;

    private string $stream;

    protected function setUp(): void
    {
        parent::setUp();

        $this->firstEvent = TestEvent::newTestEvent();
        $this->connection = TestConnection::create();
        $this->stream = Guid::generateAsHex();

        $this->connection->connect();

        $result = $this->connection->appendToStream(
            $this->stream,
            ExpectedVersion::NoStream,
            [$this->firstEvent, TestEvent::newTestEvent(), TestEvent::newTestEvent()]
        );

        $this->assertSame(2, $result->nextExpectedVersion());

        $transaction = $this->connection->startTransaction(
            $this->stream,
            2
        );

        $result = $transaction->commit();

        $this->assertSame(2, $result->nextExpectedVersion());
    }

    protected function tearDown(): void
    {
        $this->connection->close();

        parent::tearDown();
    }

    /** @test */
    public function following_append_with_correct_expected_version_are_commited_correctly(): void
    {
        $result = $this->connection->appendToStream(
            $this->stream,
            2,
            TestEvent::newAmount(2)
        );

        $this->assertSame(4, $result->nextExpectedVersion());

        $result = $this->connection->readStreamEventsForward(
            $this->stream,
            0,
            100,
            false
        );

        $this->assertSame(SliceReadStatus::Success, $result->status());
        $this->assertCount(5, $result->events());

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame($i, $result->events()[$i]->originalEventNumber());
        }
    }

    /** @test */
    public function following_append_with_expected_version_any_are_commited_correctly(): void
    {
        $result = $this->connection->appendToStream(
            $this->stream,
            ExpectedVersion::Any,
            TestEvent::newAmount(2)
        );

        $this->assertSame(4, $result->nextExpectedVersion());

        $result = $this->connection->readStreamEventsForward(
            $this->stream,
            0,
            100,
            false
        );

        $this->assertSame(SliceReadStatus::Success, $result->status());
        $this->assertCount(5, $result->events());

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame($i, $result->events()[$i]->originalEventNumber());
        }
    }

    /** @test */
    public function committing_first_event_with_expected_version_no_stream_is_idempotent(): void
    {
        $result = $this->connection->appendToStream(
            $this->stream,
            ExpectedVersion::NoStream,
            [$this->firstEvent]
        );

        $this->assertSame(0, $result->nextExpectedVersion());

        $result = $this->connection->readStreamEventsForward(
            $this->stream,
            0,
            100,
            false
        );

        $this->assertSame(SliceReadStatus::Success, $result->status());
        $this->assertCount(3, $result->events());

        for ($i = 0; $i < 3; $i++) {
            $this->assertSame($i, $result->events()[$i]->originalEventNumber());
        }
    }

    /** @test */
    public function trying_to_append_new_events_with_expected_version_no_stream_fails(): void
    {
        $this->expectException(WrongExpectedVersion::class);

        $this->connection->appendToStream(
            $this->stream,
            ExpectedVersion::NoStream,
            [TestEvent::newTestEvent()]
        );
    }
}
