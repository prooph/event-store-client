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
use Generator;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\WriteResult;
use ProophTest\EventStoreClient\Helper\EventsStream;
use ProophTest\EventStoreClient\Helper\OngoingTransaction;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;
use ProophTest\EventStoreClient\Helper\TransactionalWriter;

class appending_to_implicitly_created_stream_using_transaction extends AsyncTestCase
{
    /**
     * @test
     */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0em1_idempotent(): Generator
    {
        $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0em1_idempotent';

        $connection = TestConnection::create();
        yield $connection->connectAsync();

        $events = TestEvent::newAmount(6);
        $writer = new TransactionalWriter($connection, $stream);

        $ongoingTransaction = yield $writer->startTransaction(-1);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = yield $ongoingTransaction->writeAsync($events);
        $writeResult = yield $ongoingTransaction->commitAsync();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(5, $writeResult->nextExpectedVersion());

        $ongoingTransaction = yield $writer->startTransaction(-1);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = yield $ongoingTransaction->writeAsync([\current($events)]);
        $writeResult = yield $ongoingTransaction->commitAsync();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(0, $writeResult->nextExpectedVersion());

        $total = yield EventsStream::count($connection, $stream);
        $this->assertSame(\count($events), $total);
    }

    /**
     * @test
     */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0any_idempotent(): Generator
    {
        $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0any_idempotent';

        $connection = TestConnection::create();
        yield $connection->connectAsync();

        $events = TestEvent::newAmount(6);
        $writer = new TransactionalWriter($connection, $stream);

        $ongoingTransaction = yield $writer->startTransaction(-1);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = yield $ongoingTransaction->writeAsync($events);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = yield $ongoingTransaction->commitAsync();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(5, $writeResult->nextExpectedVersion());

        $ongoingTransaction = yield $writer->startTransaction(ExpectedVersion::ANY);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = yield $ongoingTransaction->writeAsync([\current($events)]);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = yield $ongoingTransaction->commitAsync();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(0, $writeResult->nextExpectedVersion());

        $total = yield EventsStream::count($connection, $stream);
        $this->assertSame(\count($events), $total);
    }

    /**
     * @test
     */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e5_non_idempotent(): Generator
    {
        $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e5_non_idempotent';

        $connection = TestConnection::create();
        yield $connection->connectAsync();

        $events = TestEvent::newAmount(6);
        $writer = new TransactionalWriter($connection, $stream);

        $ongoingTransaction = yield $writer->startTransaction(-1);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = yield $ongoingTransaction->writeAsync($events);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = yield $ongoingTransaction->commitAsync();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(5, $writeResult->nextExpectedVersion());

        $ongoingTransaction = yield $writer->startTransaction(5);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = yield $ongoingTransaction->writeAsync([\current($events)]);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = yield $ongoingTransaction->commitAsync();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(6, $writeResult->nextExpectedVersion());

        $total = yield EventsStream::count($connection, $stream);
        $this->assertSame(\count($events) + 1, $total);
    }

    /**
     * @test
     */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e6_wev(): Generator
    {
        $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e6_wev';

        $connection = TestConnection::create();
        yield $connection->connectAsync();

        $events = TestEvent::newAmount(6);
        $writer = new TransactionalWriter($connection, $stream);

        $ongoingTransaction = yield $writer->startTransaction(-1);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = yield $ongoingTransaction->writeAsync($events);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = yield $ongoingTransaction->commitAsync();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(5, $writeResult->nextExpectedVersion());

        $ongoingTransaction = yield $writer->startTransaction(6);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = yield $ongoingTransaction->writeAsync([\current($events)]);
        \assert($ongoingTransaction instanceof OngoingTransaction);

        $this->expectException(WrongExpectedVersion::class);

        yield $ongoingTransaction->commitAsync();
    }

    /**
     * @test
     */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e4_wev(): Generator
    {
        $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e4_wev';

        $connection = TestConnection::create();
        yield $connection->connectAsync();

        $events = TestEvent::newAmount(6);
        $writer = new TransactionalWriter($connection, $stream);

        $ongoingTransaction = yield $writer->startTransaction(-1);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = yield $ongoingTransaction->writeAsync($events);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = yield $ongoingTransaction->commitAsync();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(5, $writeResult->nextExpectedVersion());

        $ongoingTransaction = yield $writer->startTransaction(4);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = yield $ongoingTransaction->writeAsync([\current($events)]);
        \assert($ongoingTransaction instanceof OngoingTransaction);

        $this->expectException(WrongExpectedVersion::class);

        yield $ongoingTransaction->commitAsync();
    }

    /**
     * @test
     */
    public function sequence_0em1_0e0_non_idempotent(): Generator
    {
        $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_0e0_non_idempotent';

        $connection = TestConnection::create();
        yield $connection->connectAsync();

        $events = TestEvent::newAmount(1);
        $writer = new TransactionalWriter($connection, $stream);

        $ongoingTransaction = yield $writer->startTransaction(-1);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = yield $ongoingTransaction->writeAsync($events);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = yield $ongoingTransaction->commitAsync();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(0, $writeResult->nextExpectedVersion());

        $ongoingTransaction = yield $writer->startTransaction(0);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = yield $ongoingTransaction->writeAsync([\current($events)]);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = yield $ongoingTransaction->commitAsync();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(1, $writeResult->nextExpectedVersion());

        $total = yield EventsStream::count($connection, $stream);
        $this->assertSame(\count($events) + 1, $total);
    }

    /**
     * @test
     */
    public function sequence_0em1_0any_idempotent(): Generator
    {
        $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_0any_idempotent';

        $connection = TestConnection::create();
        yield $connection->connectAsync();

        $events = TestEvent::newAmount(1);
        $writer = new TransactionalWriter($connection, $stream);

        $ongoingTransaction = yield $writer->startTransaction(-1);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = yield $ongoingTransaction->writeAsync($events);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = yield $ongoingTransaction->commitAsync();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(0, $writeResult->nextExpectedVersion());

        $ongoingTransaction = yield $writer->startTransaction(ExpectedVersion::ANY);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = yield $ongoingTransaction->writeAsync([\current($events)]);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = yield $ongoingTransaction->commitAsync();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(0, $writeResult->nextExpectedVersion());

        $total = yield EventsStream::count($connection, $stream);
        $this->assertSame(\count($events), $total);
    }

    /**
     * @test
     */
    public function sequence_0em1_0em1_idempotent(): Generator
    {
        $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_0em1_idempotent';

        $connection = TestConnection::create();
        yield $connection->connectAsync();

        $events = TestEvent::newAmount(1);
        $writer = new TransactionalWriter($connection, $stream);

        $ongoingTransaction = yield $writer->startTransaction(-1);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = yield $ongoingTransaction->writeAsync($events);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = yield $ongoingTransaction->commitAsync();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(0, $writeResult->nextExpectedVersion());

        $ongoingTransaction = yield $writer->startTransaction(-1);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = yield $ongoingTransaction->writeAsync([\current($events)]);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = yield $ongoingTransaction->commitAsync();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(0, $writeResult->nextExpectedVersion());

        $total = yield EventsStream::count($connection, $stream);
        $this->assertSame(\count($events), $total);
    }

    /**
     * @test
     */
    public function sequence_0em1_1e0_2e1_1any_1any_idempotent(): Generator
    {
        $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_1e0_2e1_1any_1any_idempotent';

        $connection = TestConnection::create();
        yield $connection->connectAsync();

        $events = TestEvent::newAmount(3);
        $writer = new TransactionalWriter($connection, $stream);

        $ongoingTransaction = yield $writer->startTransaction(-1);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = yield $ongoingTransaction->writeAsync($events);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = yield $ongoingTransaction->commitAsync();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(2, $writeResult->nextExpectedVersion());

        $ongoingTransaction = yield $writer->startTransaction(ExpectedVersion::ANY);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = yield $ongoingTransaction->writeAsync([$events[1]]);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = yield $ongoingTransaction->commitAsync();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(1, $writeResult->nextExpectedVersion());

        $total = yield EventsStream::count($connection, $stream);
        $this->assertSame(\count($events), $total);
    }

    /**
     * @test
     */
    public function sequence_S_0em1_1em1_E_S_0em1_1em1_2em1_E_idempotancy_fail(): Generator
    {
        $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_S_0em1_1em1_E_S_0em1_1em1_2em1_E_idempotancy_fail';

        $connection = TestConnection::create();
        yield $connection->connectAsync();

        $events = TestEvent::newAmount(2);
        $writer = new TransactionalWriter($connection, $stream);

        $ongoingTransaction = yield $writer->startTransaction(-1);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = yield $ongoingTransaction->writeAsync($events);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $writeResult = yield $ongoingTransaction->commitAsync();
        \assert($writeResult instanceof WriteResult);

        $this->assertSame(1, $writeResult->nextExpectedVersion());

        $events[] = TestEvent::newTestEvent();

        $ongoingTransaction = yield $writer->startTransaction(-1);
        \assert($ongoingTransaction instanceof OngoingTransaction);
        $ongoingTransaction = yield $ongoingTransaction->writeAsync($events);
        \assert($ongoingTransaction instanceof OngoingTransaction);

        $this->expectException(WrongExpectedVersion::class);

        yield $ongoingTransaction->commitAsync();
    }
}
