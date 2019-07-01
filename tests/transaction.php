<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2019 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2019 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use function Amp\call;
use Amp\Parallel\Worker\DefaultPool;
use Amp\Promise;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\Async\EventStoreTransaction;
use Prooph\EventStore\EventData;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\WriteResult;
use ProophTest\EventStoreClient\Helper\ParallelTransactionTask;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;

class transaction extends TestCase
{
    /** @var EventStoreConnection */
    private $conn;

    /**
     * @throws Throwable
     */
    private function execute(callable $function): void
    {
        Promise\wait(call(function () use ($function) {
            $this->conn = TestConnection::create();

            yield $this->conn->connectAsync();

            yield from $function();

            $this->conn->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_start_on_non_existing_stream_with_correct_exp_ver_and_create_stream_on_commit(): void
    {
        $this->execute(function () {
            $stream = 'should_start_on_non_existing_stream_with_correct_exp_ver_and_create_stream_on_commit';

            $transaction = yield $this->conn->startTransactionAsync(
                $stream,
                ExpectedVersion::NO_STREAM
            );
            \assert($transaction instanceof EventStoreTransaction);

            yield $transaction->writeAsync([TestEvent::newTestEvent()]);

            $result = yield $transaction->commitAsync();
            \assert($result instanceof WriteResult);

            $this->assertSame(0, $result->nextExpectedVersion());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_start_on_non_existing_stream_with_exp_ver_any_and_create_stream_on_commit(): void
    {
        $this->execute(function () {
            $stream = 'should_start_on_non_existing_stream_with_exp_ver_any_and_create_stream_on_commit';

            $transaction = yield $this->conn->startTransactionAsync(
                $stream,
                ExpectedVersion::ANY
            );
            \assert($transaction instanceof EventStoreTransaction);

            yield $transaction->writeAsync([TestEvent::newTestEvent()]);

            $result = yield $transaction->commitAsync();
            \assert($result instanceof WriteResult);

            $this->assertSame(0, $result->nextExpectedVersion());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_fail_to_commit_non_existing_stream_with_wrong_exp_ver(): void
    {
        $this->execute(function () {
            $stream = 'should_fail_to_commit_non_existing_stream_with_wrong_exp_ver';

            $transaction = yield $this->conn->startTransactionAsync(
                $stream,
                1
            );
            \assert($transaction instanceof EventStoreTransaction);

            yield $transaction->writeAsync([TestEvent::newTestEvent()]);

            $this->expectException(WrongExpectedVersion::class);

            yield $transaction->commitAsync();
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_do_nothing_if_commits_no_events_to_empty_stream(): void
    {
        $this->execute(function () {
            $stream = 'should_do_nothing_if_commits_no_events_to_empty_stream';

            $transaction = yield $this->conn->startTransactionAsync(
                $stream,
                ExpectedVersion::NO_STREAM
            );
            \assert($transaction instanceof EventStoreTransaction);

            $result = yield $transaction->commitAsync();
            \assert($result instanceof WriteResult);

            $this->assertSame(-1, $result->nextExpectedVersion());

            $result = yield $this->conn->readStreamEventsForwardAsync(
                $stream,
                0,
                1,
                false
            );
            \assert($result instanceof StreamEventsSlice);

            $this->assertCount(0, $result->events());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_do_nothing_if_transactionally_writing_no_events_to_empty_stream(): void
    {
        $this->execute(function () {
            $stream = 'should_do_nothing_if_transactionally_writing_no_events_to_empty_stream';

            $transaction = yield $this->conn->startTransactionAsync(
                $stream,
                ExpectedVersion::NO_STREAM
            );
            \assert($transaction instanceof EventStoreTransaction);

            yield $transaction->writeAsync();

            $result = yield $transaction->commitAsync();
            \assert($result instanceof WriteResult);

            $this->assertSame(-1, $result->nextExpectedVersion());

            $result = yield $this->conn->readStreamEventsForwardAsync(
                $stream,
                0,
                1,
                false
            );
            \assert($result instanceof StreamEventsSlice);

            $this->assertCount(0, $result->events());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_validate_expectations_on_commit(): void
    {
        $this->execute(function () {
            $stream = 'should_validate_expectations_on_commit';

            $transaction = yield $this->conn->startTransactionAsync(
                $stream,
                100500
            );
            \assert($transaction instanceof EventStoreTransaction);

            yield $transaction->writeAsync([TestEvent::newTestEvent()]);

            $this->expectException(WrongExpectedVersion::class);

            yield $transaction->commitAsync();
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_commit_when_writing_with_exp_ver_any_even_while_someone_is_writing_in_parallel(): void
    {
        $this->execute(function () {
            $stream = 'should_commit_when_writing_with_exp_ver_any_even_while_someone_is_writing_in_parallel';

            $task1 = new ParallelTransactionTask($stream, 'trans write');
            $task2 = new ParallelTransactionTask($stream, 'plain write');

            $pool = new DefaultPool();

            $results = yield Promise\all([
                $pool->enqueue($task1),
                $pool->enqueue($task2),
            ]);

            $this->assertCount(2, $results);
            $this->assertTrue($results[0]);
            $this->assertTrue($results[1]);

            $store = TestConnection::create();
            yield $store->connectAsync();

            $slice = yield $store->readStreamEventsForwardAsync(
                $stream,
                0,
                500,
                false
            );
            \assert($slice instanceof StreamEventsSlice);

            $this->assertCount(500, $slice->events());

            $totalTransWrites = 0;
            $totalPlainWrites = 0;

            foreach ($slice->events() as $event) {
                if ($event->event()->metadata() === 'trans write') {
                    $totalTransWrites++;
                }

                if ($event->event()->metadata() === 'plain write') {
                    $totalPlainWrites++;
                }
            }

            $this->assertSame(250, $totalTransWrites);
            $this->assertSame(250, $totalPlainWrites);
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_fail_to_commit_if_started_with_correct_ver_but_committing_with_bad(): void
    {
        $this->execute(function () {
            $stream = 'should_fail_to_commit_if_started_with_correct_ver_but_committing_with_bad';

            $transaction = yield $this->conn->startTransactionAsync(
                $stream,
                ExpectedVersion::EMPTY_STREAM
            );
            \assert($transaction instanceof EventStoreTransaction);

            yield $this->conn->appendToStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, [TestEvent::newTestEvent()]);

            yield $transaction->writeAsync([TestEvent::newTestEvent()]);

            $this->expectException(WrongExpectedVersion::class);

            yield $transaction->commitAsync();
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_not_fail_to_commit_if_started_with_wrong_ver_but_committing_with_correct_ver(): void
    {
        $this->execute(function () {
            $stream = 'should_not_fail_to_commit_if_started_with_wrong_ver_but_committing_with_correct_ver';

            $transaction = yield $this->conn->startTransactionAsync(
                $stream,
                0
            );
            \assert($transaction instanceof EventStoreTransaction);

            yield $this->conn->appendToStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, [TestEvent::newTestEvent()]);

            yield $transaction->writeAsync([TestEvent::newTestEvent()]);

            $result = yield $transaction->commitAsync();
            \assert($result instanceof WriteResult);

            $this->assertSame(1, $result->nextExpectedVersion());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_fail_to_commit_if_started_with_correct_ver_but_on_commit_stream_was_deleted(): void
    {
        $this->execute(function () {
            $stream = 'should_fail_to_commit_if_started_with_correct_ver_but_on_commit_stream_was_deleted';

            $transaction = yield $this->conn->startTransactionAsync(
                $stream,
                ExpectedVersion::EMPTY_STREAM
            );
            \assert($transaction instanceof EventStoreTransaction);

            yield $transaction->writeAsync([TestEvent::newTestEvent()]);

            yield $this->conn->deleteStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, true);

            $this->expectException(StreamDeleted::class);

            yield $transaction->commitAsync();
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function idempotency_is_correct_for_explicit_transactions_with_expected_version_any(): void
    {
        $this->execute(function () {
            $stream = 'idempotency_is_correct_for_explicit_transactions_with_expected_version_any';

            $event = new EventData(null, 'SomethingHappened', true, '{Value:42}');

            $transaction1 = yield $this->conn->startTransactionAsync(
                $stream,
                ExpectedVersion::ANY
            );
            \assert($transaction1 instanceof EventStoreTransaction);
            yield $transaction1->writeAsync([$event]);
            $result1 = yield $transaction1->commitAsync();
            \assert($result1 instanceof WriteResult);
            $this->assertEquals(0, $result1->nextExpectedVersion());

            $transaction2 = yield $this->conn->startTransactionAsync(
                $stream,
                ExpectedVersion::ANY
            );
            \assert($transaction2 instanceof EventStoreTransaction);
            yield $transaction2->writeAsync([$event]);
            $result2 = yield $transaction2->commitAsync();
            \assert($result2 instanceof WriteResult);
            $this->assertEquals(0, $result2->nextExpectedVersion());

            $result = yield $this->conn->readStreamEventsForwardAsync(
                $stream,
                0,
                100,
                false
            );
            \assert($result instanceof StreamEventsSlice);

            $this->assertCount(1, $result->events());
            $this->assertTrue($event->eventId()->equals($result->events()[0]->event()->eventId()));
        });
    }
}
