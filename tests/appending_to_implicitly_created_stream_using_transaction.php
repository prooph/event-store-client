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
use Prooph\EventStoreClient\Exception\WrongExpectedVersionException;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\WriteResult;
use ProophTest\EventStoreClient\Helper\EventsStream;
use ProophTest\EventStoreClient\Helper\OngoingTransaction;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;
use ProophTest\EventStoreClient\Helper\TransactionalWriter;
use Throwable;
use function Amp\call;
use function Amp\Promise\wait;

class appending_to_implicitly_created_stream_using_transaction extends TestCase
{
    /**
     * @test
     * @throws Throwable
     */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0em1_idempotent(): void
    {
        wait(call(function () {
            $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0em1_idempotent';

            $connection = TestConnection::createAsync();
            yield $connection->connectAsync();

            $events = TestEvent::newAmount(6);
            $writer = new TransactionalWriter($connection, $stream);

            /** @var OngoingTransaction $ongoingTransaction */
            $ongoingTransaction = yield $writer->startTransaction(-1);
            $ongoingTransaction = yield $ongoingTransaction->writeAsync($events);
            /** @var WriteResult $writeResult */
            $writeResult = yield $ongoingTransaction->commitAsync();

            $this->assertSame(5, $writeResult->nextExpectedVersion());

            /** @var OngoingTransaction $ongoingTransaction */
            $ongoingTransaction = yield $writer->startTransaction(-1);
            $ongoingTransaction = yield $ongoingTransaction->writeAsync([\current($events)]);
            /** @var WriteResult $writeResult */
            $writeResult = yield $ongoingTransaction->commitAsync();

            $this->assertSame(0, $writeResult->nextExpectedVersion());

            $total = yield EventsStream::count($connection, $stream);
            $this->assertSame(\count($events), $total);
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0any_idempotent(): void
    {
        wait(call(function () {
            $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0any_idempotent';

            $connection = TestConnection::createAsync();
            yield $connection->connectAsync();

            $events = TestEvent::newAmount(6);
            $writer = new TransactionalWriter($connection, $stream);

            /** @var OngoingTransaction $ongoingTransaction */
            $ongoingTransaction = yield $writer->startTransaction(-1);
            $ongoingTransaction = yield $ongoingTransaction->writeAsync($events);
            /** @var WriteResult $writeResult */
            $writeResult = yield $ongoingTransaction->commitAsync();

            $this->assertSame(5, $writeResult->nextExpectedVersion());

            /** @var OngoingTransaction $ongoingTransaction */
            $ongoingTransaction = yield $writer->startTransaction(ExpectedVersion::ANY);
            $ongoingTransaction = yield $ongoingTransaction->writeAsync([\current($events)]);
            /** @var WriteResult $writeResult */
            $writeResult = yield $ongoingTransaction->commitAsync();

            $this->assertSame(0, $writeResult->nextExpectedVersion());

            $total = yield EventsStream::count($connection, $stream);
            $this->assertSame(\count($events), $total);
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e5_non_idempotent(): void
    {
        wait(call(function () {
            $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e5_non_idempotent';

            $connection = TestConnection::createAsync();
            yield $connection->connectAsync();

            $events = TestEvent::newAmount(6);
            $writer = new TransactionalWriter($connection, $stream);

            /** @var OngoingTransaction $ongoingTransaction */
            $ongoingTransaction = yield $writer->startTransaction(-1);
            $ongoingTransaction = yield $ongoingTransaction->writeAsync($events);
            /** @var WriteResult $writeResult */
            $writeResult = yield $ongoingTransaction->commitAsync();

            $this->assertSame(5, $writeResult->nextExpectedVersion());

            /** @var OngoingTransaction $ongoingTransaction */
            $ongoingTransaction = yield $writer->startTransaction(5);
            $ongoingTransaction = yield $ongoingTransaction->writeAsync([\current($events)]);
            /** @var WriteResult $writeResult */
            $writeResult = yield $ongoingTransaction->commitAsync();

            $this->assertSame(6, $writeResult->nextExpectedVersion());

            $total = yield EventsStream::count($connection, $stream);
            $this->assertSame(\count($events) + 1, $total);
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e6_wev(): void
    {
        wait(call(function () {
            $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e6_wev';

            $connection = TestConnection::createAsync();
            yield $connection->connectAsync();

            $events = TestEvent::newAmount(6);
            $writer = new TransactionalWriter($connection, $stream);

            /** @var OngoingTransaction $ongoingTransaction */
            $ongoingTransaction = yield $writer->startTransaction(-1);
            $ongoingTransaction = yield $ongoingTransaction->writeAsync($events);
            /** @var WriteResult $writeResult */
            $writeResult = yield $ongoingTransaction->commitAsync();

            $this->assertSame(5, $writeResult->nextExpectedVersion());

            /** @var OngoingTransaction $ongoingTransaction */
            $ongoingTransaction = yield $writer->startTransaction(6);
            $ongoingTransaction = yield $ongoingTransaction->writeAsync([\current($events)]);

            $this->expectException(WrongExpectedVersionException::class);

            yield $ongoingTransaction->commitAsync();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e4_wev(): void
    {
        wait(call(function () {
            $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_1e0_2e1_3e2_4e3_5e4_0e4_wev';

            $connection = TestConnection::createAsync();
            yield $connection->connectAsync();

            $events = TestEvent::newAmount(6);
            $writer = new TransactionalWriter($connection, $stream);

            /** @var OngoingTransaction $ongoingTransaction */
            $ongoingTransaction = yield $writer->startTransaction(-1);
            $ongoingTransaction = yield $ongoingTransaction->writeAsync($events);
            /** @var WriteResult $writeResult */
            $writeResult = yield $ongoingTransaction->commitAsync();

            $this->assertSame(5, $writeResult->nextExpectedVersion());

            /** @var OngoingTransaction $ongoingTransaction */
            $ongoingTransaction = yield $writer->startTransaction(4);
            $ongoingTransaction = yield $ongoingTransaction->writeAsync([\current($events)]);

            $this->expectException(WrongExpectedVersionException::class);

            yield $ongoingTransaction->commitAsync();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function sequence_0em1_0e0_non_idempotent(): void
    {
        wait(call(function () {
            $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_0e0_non_idempotent';

            $connection = TestConnection::createAsync();
            yield $connection->connectAsync();

            $events = TestEvent::newAmount(1);
            $writer = new TransactionalWriter($connection, $stream);

            /** @var OngoingTransaction $ongoingTransaction */
            $ongoingTransaction = yield $writer->startTransaction(-1);
            $ongoingTransaction = yield $ongoingTransaction->writeAsync($events);
            /** @var WriteResult $writeResult */
            $writeResult = yield $ongoingTransaction->commitAsync();

            $this->assertSame(0, $writeResult->nextExpectedVersion());

            /** @var OngoingTransaction $ongoingTransaction */
            $ongoingTransaction = yield $writer->startTransaction(0);
            $ongoingTransaction = yield $ongoingTransaction->writeAsync([\current($events)]);
            /** @var WriteResult $writeResult */
            $writeResult = yield $ongoingTransaction->commitAsync();

            $this->assertSame(1, $writeResult->nextExpectedVersion());

            $total = yield EventsStream::count($connection, $stream);
            $this->assertSame(\count($events) + 1, $total);
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function sequence_0em1_0any_idempotent(): void
    {
        wait(call(function () {
            $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_0any_idempotent';

            $connection = TestConnection::createAsync();
            yield $connection->connectAsync();

            $events = TestEvent::newAmount(1);
            $writer = new TransactionalWriter($connection, $stream);

            /** @var OngoingTransaction $ongoingTransaction */
            $ongoingTransaction = yield $writer->startTransaction(-1);
            $ongoingTransaction = yield $ongoingTransaction->writeAsync($events);
            /** @var WriteResult $writeResult */
            $writeResult = yield $ongoingTransaction->commitAsync();

            $this->assertSame(0, $writeResult->nextExpectedVersion());

            /** @var OngoingTransaction $ongoingTransaction */
            $ongoingTransaction = yield $writer->startTransaction(ExpectedVersion::ANY);
            $ongoingTransaction = yield $ongoingTransaction->writeAsync([\current($events)]);
            /** @var WriteResult $writeResult */
            $writeResult = yield $ongoingTransaction->commitAsync();

            $this->assertSame(0, $writeResult->nextExpectedVersion());

            $total = yield EventsStream::count($connection, $stream);
            $this->assertSame(\count($events), $total);
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function sequence_0em1_0em1_idempotent(): void
    {
        wait(call(function () {
            $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_0em1_idempotent';

            $connection = TestConnection::createAsync();
            yield $connection->connectAsync();

            $events = TestEvent::newAmount(1);
            $writer = new TransactionalWriter($connection, $stream);

            /** @var OngoingTransaction $ongoingTransaction */
            $ongoingTransaction = yield $writer->startTransaction(-1);
            $ongoingTransaction = yield $ongoingTransaction->writeAsync($events);
            /** @var WriteResult $writeResult */
            $writeResult = yield $ongoingTransaction->commitAsync();

            $this->assertSame(0, $writeResult->nextExpectedVersion());

            /** @var OngoingTransaction $ongoingTransaction */
            $ongoingTransaction = yield $writer->startTransaction(-1);
            $ongoingTransaction = yield $ongoingTransaction->writeAsync([\current($events)]);
            /** @var WriteResult $writeResult */
            $writeResult = yield $ongoingTransaction->commitAsync();

            $this->assertSame(0, $writeResult->nextExpectedVersion());

            $total = yield EventsStream::count($connection, $stream);
            $this->assertSame(\count($events), $total);
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function sequence_0em1_1e0_2e1_1any_1any_idempotent(): void
    {
        wait(call(function () {
            $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_0em1_1e0_2e1_1any_1any_idempotent';

            $connection = TestConnection::createAsync();
            yield $connection->connectAsync();

            $events = TestEvent::newAmount(3);
            $writer = new TransactionalWriter($connection, $stream);

            /** @var OngoingTransaction $ongoingTransaction */
            $ongoingTransaction = yield $writer->startTransaction(-1);
            $ongoingTransaction = yield $ongoingTransaction->writeAsync($events);
            /** @var WriteResult $writeResult */
            $writeResult = yield $ongoingTransaction->commitAsync();

            $this->assertSame(2, $writeResult->nextExpectedVersion());

            /** @var OngoingTransaction $ongoingTransaction */
            $ongoingTransaction = yield $writer->startTransaction(ExpectedVersion::ANY);
            $ongoingTransaction = yield $ongoingTransaction->writeAsync([$events[1]]);
            /** @var WriteResult $writeResult */
            $writeResult = yield $ongoingTransaction->commitAsync();

            $this->assertSame(1, $writeResult->nextExpectedVersion());

            $total = yield EventsStream::count($connection, $stream);
            $this->assertSame(\count($events), $total);
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function sequence_S_0em1_1em1_E_S_0em1_1em1_2em1_E_idempotancy_fail(): void
    {
        wait(call(function () {
            $stream = 'appending_to_implicitly_created_stream_using_transaction_sequence_S_0em1_1em1_E_S_0em1_1em1_2em1_E_idempotancy_fail';

            $connection = TestConnection::createAsync();
            yield $connection->connectAsync();

            $events = TestEvent::newAmount(2);
            $writer = new TransactionalWriter($connection, $stream);

            /** @var OngoingTransaction $ongoingTransaction */
            $ongoingTransaction = yield $writer->startTransaction(-1);
            $ongoingTransaction = yield $ongoingTransaction->writeAsync($events);
            /** @var WriteResult $writeResult */
            $writeResult = yield $ongoingTransaction->commitAsync();

            $this->assertSame(1, $writeResult->nextExpectedVersion());

            $events[] = TestEvent::new();

            /** @var OngoingTransaction $ongoingTransaction */
            $ongoingTransaction = yield $writer->startTransaction(-1);
            $ongoingTransaction = yield $ongoingTransaction->writeAsync($events);

            $this->expectException(WrongExpectedVersionException::class);

            yield $ongoingTransaction->commitAsync();
        }));
    }
}
