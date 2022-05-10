<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2022 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2022 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\WriteResult;
use ProophTest\EventStoreClient\Helper\EventsStream;
use ProophTest\EventStoreClient\Helper\OngoingTransaction;
use ProophTest\EventStoreClient\Helper\TestEvent;
use ProophTest\EventStoreClient\Helper\TransactionalWriter;

class appending_to_implicitly_created_stream_using_transaction extends EventStoreConnectionTestCase
{
    /** @test */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0em1_idempotent(): void
    {
        $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0em1_idempotent';

        $events = TestEvent::newAmount(6);
        $writer = new TransactionalWriter($this->connection, $stream);

        $ongoingTransaction = $writer->startTransaction(-1);
        $ongoingTransaction = $ongoingTransaction->write($events);
        $writeResult = $ongoingTransaction->commit();

        $this->assertSame(5, $writeResult->nextExpectedVersion());

        $ongoingTransaction = $writer->startTransaction(-1);
        $ongoingTransaction = $ongoingTransaction->write([\current($events)]);
        $writeResult = $ongoingTransaction->commit();

        $this->assertSame(0, $writeResult->nextExpectedVersion());

        $total = EventsStream::count($this->connection, $stream);
        $this->assertSame(\count($events), $total);
    }

    /** @test */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0any_idempotent(): void
    {
        $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0any_idempotent';

        $events = TestEvent::newAmount(6);
        $writer = new TransactionalWriter($this->connection, $stream);

        $ongoingTransaction = $writer->startTransaction(-1);
        $ongoingTransaction = $ongoingTransaction->write($events);
        $writeResult = $ongoingTransaction->commit();

        $this->assertSame(5, $writeResult->nextExpectedVersion());

        $ongoingTransaction = $writer->startTransaction(ExpectedVersion::Any);
        $ongoingTransaction = $ongoingTransaction->write([\current($events)]);
        $writeResult = $ongoingTransaction->commit();

        $this->assertSame(0, $writeResult->nextExpectedVersion());

        $total = EventsStream::count($this->connection, $stream);
        $this->assertSame(\count($events), $total);
    }

    /** @test */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e5_non_idempotent(): void
    {
        $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e5_non_idempotent';

        $events = TestEvent::newAmount(6);
        $writer = new TransactionalWriter($this->connection, $stream);

        $ongoingTransaction = $writer->startTransaction(-1);
        $ongoingTransaction = $ongoingTransaction->write($events);
        $writeResult = $ongoingTransaction->commit();

        $this->assertSame(5, $writeResult->nextExpectedVersion());

        $ongoingTransaction = $writer->startTransaction(5);
        $ongoingTransaction = $ongoingTransaction->write([\current($events)]);
        $writeResult = $ongoingTransaction->commit();

        $this->assertSame(6, $writeResult->nextExpectedVersion());

        $total = EventsStream::count($this->connection, $stream);
        $this->assertSame(\count($events) + 1, $total);
    }

    /** @test */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e6_wev(): void
    {
        $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e6_wev';

        $events = TestEvent::newAmount(6);
        $writer = new TransactionalWriter($this->connection, $stream);

        $ongoingTransaction = $writer->startTransaction(-1);
        $ongoingTransaction = $ongoingTransaction->write($events);
        $writeResult = $ongoingTransaction->commit();

        $this->assertSame(5, $writeResult->nextExpectedVersion());

        $ongoingTransaction = $writer->startTransaction(6);
        $ongoingTransaction = $ongoingTransaction->write([\current($events)]);
        \assert($ongoingTransaction instanceof OngoingTransaction);

        $this->expectException(WrongExpectedVersion::class);

        $ongoingTransaction->commit();
    }

    /** @test */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e4_wev(): void
    {
        $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e4_wev';

        $events = TestEvent::newAmount(6);
        $writer = new TransactionalWriter($this->connection, $stream);

        $ongoingTransaction = $writer->startTransaction(-1);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = $ongoingTransaction->write($events);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = $ongoingTransaction->commit();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(5, $writeResult->nextExpectedVersion());

        $ongoingTransaction = $writer->startTransaction(4);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = $ongoingTransaction->write([\current($events)]);
        \assert($ongoingTransaction instanceof OngoingTransaction);

        $this->expectException(WrongExpectedVersion::class);

        $ongoingTransaction->commit();
    }

    /** @test */
    public function sequence_0em1_0e0_non_idempotent(): void
    {
        $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_0e0_non_idempotent';

        $events = TestEvent::newAmount(1);
        $writer = new TransactionalWriter($this->connection, $stream);

        $ongoingTransaction = $writer->startTransaction(-1);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = $ongoingTransaction->write($events);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = $ongoingTransaction->commit();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(0, $writeResult->nextExpectedVersion());

        $ongoingTransaction = $writer->startTransaction(0);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = $ongoingTransaction->write([\current($events)]);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = $ongoingTransaction->commit();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(1, $writeResult->nextExpectedVersion());

        $total = EventsStream::count($this->connection, $stream);
        $this->assertSame(\count($events) + 1, $total);
    }

    /** @test */
    public function sequence_0em1_0any_idempotent(): void
    {
        $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_0any_idempotent';

        $events = TestEvent::newAmount(1);
        $writer = new TransactionalWriter($this->connection, $stream);

        $ongoingTransaction = $writer->startTransaction(-1);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = $ongoingTransaction->write($events);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = $ongoingTransaction->commit();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(0, $writeResult->nextExpectedVersion());

        $ongoingTransaction = $writer->startTransaction(ExpectedVersion::Any);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = $ongoingTransaction->write([\current($events)]);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = $ongoingTransaction->commit();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(0, $writeResult->nextExpectedVersion());

        $total = EventsStream::count($this->connection, $stream);
        $this->assertSame(\count($events), $total);
    }

    /** @test */
    public function sequence_0em1_0em1_idempotent(): void
    {
        $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_0em1_idempotent';

        $events = TestEvent::newAmount(1);
        $writer = new TransactionalWriter($this->connection, $stream);

        $ongoingTransaction = $writer->startTransaction(-1);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = $ongoingTransaction->write($events);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = $ongoingTransaction->commit();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(0, $writeResult->nextExpectedVersion());

        $ongoingTransaction = $writer->startTransaction(-1);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = $ongoingTransaction->write([\current($events)]);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = $ongoingTransaction->commit();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(0, $writeResult->nextExpectedVersion());

        $total = EventsStream::count($this->connection, $stream);
        $this->assertSame(\count($events), $total);
    }

    /** @test */
    public function sequence_0em1_1e0_2e1_1any_1any_idempotent(): void
    {
        $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_1e0_2e1_1any_1any_idempotent';

        $events = TestEvent::newAmount(3);
        $writer = new TransactionalWriter($this->connection, $stream);

        $ongoingTransaction = $writer->startTransaction(-1);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = $ongoingTransaction->write($events);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = $ongoingTransaction->commit();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(2, $writeResult->nextExpectedVersion());

        $ongoingTransaction = $writer->startTransaction(ExpectedVersion::Any);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = $ongoingTransaction->write([$events[1]]);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = $ongoingTransaction->commit();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(1, $writeResult->nextExpectedVersion());

        $total = EventsStream::count($this->connection, $stream);
        $this->assertSame(\count($events), $total);
    }

    /** @test */
    public function sequence_S_0em1_1em1_E_S_0em1_1em1_2em1_E_idempotancy_fail(): void
    {
        $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_S_0em1_1em1_E_S_0em1_1em1_2em1_E_idempotancy_fail';

        $events = TestEvent::newAmount(2);
        $writer = new TransactionalWriter($this->connection, $stream);

        $ongoingTransaction = $writer->startTransaction(-1);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = $ongoingTransaction->write($events);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = $ongoingTransaction->commit();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(1, $writeResult->nextExpectedVersion());

        $events[] = TestEvent::newTestEvent();

        $ongoingTransaction = $writer->startTransaction(-1);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = $ongoingTransaction->write($events);
        \assert($ongoingTransaction instanceof OngoingTransaction);

        $this->expectException(WrongExpectedVersion::class);

        $ongoingTransaction->commit();
    }
}
