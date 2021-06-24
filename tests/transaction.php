<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2021 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2021 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\Parallel\Worker\DefaultPool;
use Amp\Promise;
use Generator;
use Prooph\EventStore\Async\EventStoreTransaction;
use Prooph\EventStore\EventData;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\WriteResult;
use ProophTest\EventStoreClient\Helper\ParallelTransactionTask;
use ProophTest\EventStoreClient\Helper\TestEvent;

class transaction extends EventStoreConnectionTestCase
{
    /** @test */
    public function should_start_on_non_existing_stream_with_correct_exp_ver_and_create_stream_on_commit(): Generator
    {
        $stream = 'should_start_on_non_existing_stream_with_correct_exp_ver_and_create_stream_on_commit';

        $transaction = yield $this->connection->startTransactionAsync(
            $stream,
            ExpectedVersion::NO_STREAM
        );
        \assert($transaction instanceof EventStoreTransaction);

        yield $transaction->writeAsync([TestEvent::newTestEvent()]);

        $result = yield $transaction->commitAsync();
        \assert($result instanceof WriteResult);

        $this->assertSame(0, $result->nextExpectedVersion());
    }

    /** @test */
    public function should_start_on_non_existing_stream_with_exp_ver_any_and_create_stream_on_commit(): Generator
    {
        $stream = 'should_start_on_non_existing_stream_with_exp_ver_any_and_create_stream_on_commit';

        $transaction = yield $this->connection->startTransactionAsync(
            $stream,
            ExpectedVersion::ANY
        );
        \assert($transaction instanceof EventStoreTransaction);

        yield $transaction->writeAsync([TestEvent::newTestEvent()]);

        $result = yield $transaction->commitAsync();
        \assert($result instanceof WriteResult);

        $this->assertSame(0, $result->nextExpectedVersion());
    }

    /** @test */
    public function should_fail_to_commit_non_existing_stream_with_wrong_exp_ver(): Generator
    {
        $stream = 'should_fail_to_commit_non_existing_stream_with_wrong_exp_ver';

        $transaction = yield $this->connection->startTransactionAsync(
            $stream,
            1
        );
        \assert($transaction instanceof EventStoreTransaction);

        yield $transaction->writeAsync([TestEvent::newTestEvent()]);

        $this->expectException(WrongExpectedVersion::class);

        yield $transaction->commitAsync();
    }

    /** @test */
    public function should_do_nothing_if_commits_no_events_to_empty_stream(): Generator
    {
        $stream = 'should_do_nothing_if_commits_no_events_to_empty_stream';

        $transaction = yield $this->connection->startTransactionAsync(
            $stream,
            ExpectedVersion::NO_STREAM
        );
        \assert($transaction instanceof EventStoreTransaction);

        $result = yield $transaction->commitAsync();
        \assert($result instanceof WriteResult);

        $this->assertSame(-1, $result->nextExpectedVersion());

        $result = yield $this->connection->readStreamEventsForwardAsync(
            $stream,
            0,
            1,
            false
        );
        \assert($result instanceof StreamEventsSlice);

        $this->assertCount(0, $result->events());
    }

    /** @test */
    public function should_do_nothing_if_transactionally_writing_no_events_to_empty_stream(): Generator
    {
        $stream = 'should_do_nothing_if_transactionally_writing_no_events_to_empty_stream';

        $transaction = yield $this->connection->startTransactionAsync(
            $stream,
            ExpectedVersion::NO_STREAM
        );
        \assert($transaction instanceof EventStoreTransaction);

        yield $transaction->writeAsync();

        $result = yield $transaction->commitAsync();
        \assert($result instanceof WriteResult);

        $this->assertSame(-1, $result->nextExpectedVersion());

        $result = yield $this->connection->readStreamEventsForwardAsync(
            $stream,
            0,
            1,
            false
        );
        \assert($result instanceof StreamEventsSlice);

        $this->assertCount(0, $result->events());
    }

    /** @test */
    public function should_validate_expectations_on_commit(): Generator
    {
        $stream = 'should_validate_expectations_on_commit';

        $transaction = yield $this->connection->startTransactionAsync(
            $stream,
            100500
        );
        \assert($transaction instanceof EventStoreTransaction);

        yield $transaction->writeAsync([TestEvent::newTestEvent()]);

        $this->expectException(WrongExpectedVersion::class);

        yield $transaction->commitAsync();
    }

    /** @test */
    public function should_commit_when_writing_with_exp_ver_any_even_while_someone_is_writing_in_parallel(): Generator
    {
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

        $slice = yield $this->connection->readStreamEventsForwardAsync(
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
    }

    /** @test */
    public function should_fail_to_commit_if_started_with_correct_ver_but_committing_with_bad(): Generator
    {
        $stream = 'should_fail_to_commit_if_started_with_correct_ver_but_committing_with_bad';

        $transaction = yield $this->connection->startTransactionAsync(
            $stream,
            ExpectedVersion::NO_STREAM
        );
        \assert($transaction instanceof EventStoreTransaction);

        yield $this->connection->appendToStreamAsync($stream, ExpectedVersion::NO_STREAM, [TestEvent::newTestEvent()]);

        yield $transaction->writeAsync([TestEvent::newTestEvent()]);

        $this->expectException(WrongExpectedVersion::class);

        yield $transaction->commitAsync();
    }

    /** @test */
    public function should_not_fail_to_commit_if_started_with_wrong_ver_but_committing_with_correct_ver(): Generator
    {
        $stream = 'should_not_fail_to_commit_if_started_with_wrong_ver_but_committing_with_correct_ver';

        $transaction = yield $this->connection->startTransactionAsync(
            $stream,
            0
        );
        \assert($transaction instanceof EventStoreTransaction);

        yield $this->connection->appendToStreamAsync($stream, ExpectedVersion::NO_STREAM, [TestEvent::newTestEvent()]);

        yield $transaction->writeAsync([TestEvent::newTestEvent()]);

        $result = yield $transaction->commitAsync();
        \assert($result instanceof WriteResult);

        $this->assertSame(1, $result->nextExpectedVersion());
    }

    /** @test */
    public function should_fail_to_commit_if_started_with_correct_ver_but_on_commit_stream_was_deleted(): Generator
    {
        $stream = 'should_fail_to_commit_if_started_with_correct_ver_but_on_commit_stream_was_deleted';

        $transaction = yield $this->connection->startTransactionAsync(
            $stream,
            ExpectedVersion::NO_STREAM
        );
        \assert($transaction instanceof EventStoreTransaction);

        yield $transaction->writeAsync([TestEvent::newTestEvent()]);

        yield $this->connection->deleteStreamAsync($stream, ExpectedVersion::NO_STREAM, true);

        $this->expectException(StreamDeleted::class);

        yield $transaction->commitAsync();
    }

    /** @test */
    public function idempotency_is_correct_for_explicit_transactions_with_expected_version_any(): Generator
    {
        $stream = 'idempotency_is_correct_for_explicit_transactions_with_expected_version_any';

        $event = new EventData(null, 'SomethingHappened', true, '{Value:42}');

        $transaction1 = yield $this->connection->startTransactionAsync(
            $stream,
            ExpectedVersion::ANY
        );
        \assert($transaction1 instanceof EventStoreTransaction);
        yield $transaction1->writeAsync([$event]);
        $result1 = yield $transaction1->commitAsync();
        \assert($result1 instanceof WriteResult);
        $this->assertSame(0, $result1->nextExpectedVersion());

        $transaction2 = yield $this->connection->startTransactionAsync(
            $stream,
            ExpectedVersion::ANY
        );
        \assert($transaction2 instanceof EventStoreTransaction);
        yield $transaction2->writeAsync([$event]);
        $result2 = yield $transaction2->commitAsync();
        \assert($result2 instanceof WriteResult);
        $this->assertSame(0, $result2->nextExpectedVersion());

        $result = yield $this->connection->readStreamEventsForwardAsync(
            $stream,
            0,
            100,
            false
        );
        \assert($result instanceof StreamEventsSlice);

        $this->assertCount(1, $result->events());
        $this->assertTrue($event->eventId()->equals($result->events()[0]->event()->eventId()));
    }
}
