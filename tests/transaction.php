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

use Amp\Future;
use Amp\Parallel\Worker\DefaultWorkerPool;
use Prooph\EventStore\EventData;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
use ProophTest\EventStoreClient\Helper\ParallelTransactionTask;
use ProophTest\EventStoreClient\Helper\TestEvent;

class transaction extends EventStoreConnectionTestCase
{
    /** @test */
    public function should_start_on_non_existing_stream_with_correct_exp_ver_and_create_stream_on_commit(): void
    {
        $stream = 'should_start_on_non_existing_stream_with_correct_exp_ver_and_create_stream_on_commit';

        $transaction = $this->connection->startTransaction(
            $stream,
            ExpectedVersion::NoStream
        );

        $transaction->write([TestEvent::newTestEvent()]);

        $result = $transaction->commit();

        $this->assertSame(0, $result->nextExpectedVersion());
    }

    /** @test */
    public function should_start_on_non_existing_stream_with_exp_ver_any_and_create_stream_on_commit(): void
    {
        $stream = 'should_start_on_non_existing_stream_with_exp_ver_any_and_create_stream_on_commit';

        $transaction = $this->connection->startTransaction(
            $stream,
            ExpectedVersion::Any
        );

        $transaction->write([TestEvent::newTestEvent()]);

        $result = $transaction->commit();

        $this->assertSame(0, $result->nextExpectedVersion());
    }

    /** @test */
    public function should_fail_to_commit_non_existing_stream_with_wrong_exp_ver(): void
    {
        $stream = 'should_fail_to_commit_non_existing_stream_with_wrong_exp_ver';

        $transaction = $this->connection->startTransaction(
            $stream,
            1
        );

        $transaction->write([TestEvent::newTestEvent()]);

        $this->expectException(WrongExpectedVersion::class);

        $transaction->commit();
    }

    /** @test */
    public function should_do_nothing_if_commits_no_events_to_empty_stream(): void
    {
        $stream = 'should_do_nothing_if_commits_no_events_to_empty_stream';

        $transaction = $this->connection->startTransaction(
            $stream,
            ExpectedVersion::NoStream
        );

        $result = $transaction->commit();

        $this->assertSame(-1, $result->nextExpectedVersion());

        $result = $this->connection->readStreamEventsForward(
            $stream,
            0,
            1,
            false
        );

        $this->assertCount(0, $result->events());
    }

    /** @test */
    public function should_do_nothing_if_transactionally_writing_no_events_to_empty_stream(): void
    {
        $stream = 'should_do_nothing_if_transactionally_writing_no_events_to_empty_stream';

        $transaction = $this->connection->startTransaction(
            $stream,
            ExpectedVersion::NoStream
        );

        $transaction->write();

        $result = $transaction->commit();

        $this->assertSame(-1, $result->nextExpectedVersion());

        $result = $this->connection->readStreamEventsForward(
            $stream,
            0,
            1,
            false
        );

        $this->assertCount(0, $result->events());
    }

    /** @test */
    public function should_validate_expectations_on_commit(): void
    {
        $stream = 'should_validate_expectations_on_commit';

        $transaction = $this->connection->startTransaction(
            $stream,
            100500
        );

        $transaction->write([TestEvent::newTestEvent()]);

        $this->expectException(WrongExpectedVersion::class);

        $transaction->commit();
    }

    /** @test */
    public function should_commit_when_writing_with_exp_ver_any_even_while_someone_is_writing_in_parallel(): void
    {
        $stream = 'should_commit_when_writing_with_exp_ver_any_even_while_someone_is_writing_in_parallel';

        $task1 = new ParallelTransactionTask($stream, 'trans write');
        $task2 = new ParallelTransactionTask($stream, 'plain write');

        $pool = new DefaultWorkerPool();

        $results = [];
        $results[] = $pool->submit($task1)->getResult();
        $results[] = $pool->submit($task2)->getResult();

        $results = Future\awaitAll($results);

        $this->assertCount(2, $results[1]);
        $this->assertTrue($results[1][0]);
        $this->assertTrue($results[1][1]);

        $slice = $this->connection->readStreamEventsForward(
            $stream,
            0,
            500,
            false
        );

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
    public function should_fail_to_commit_if_started_with_correct_ver_but_committing_with_bad(): void
    {
        $stream = 'should_fail_to_commit_if_started_with_correct_ver_but_committing_with_bad';

        $transaction = $this->connection->startTransaction(
            $stream,
            ExpectedVersion::NoStream
        );

        $this->connection->appendToStream($stream, ExpectedVersion::NoStream, [TestEvent::newTestEvent()]);

        $transaction->write([TestEvent::newTestEvent()]);

        $this->expectException(WrongExpectedVersion::class);

        $transaction->commit();
    }

    /** @test */
    public function should_not_fail_to_commit_if_started_with_wrong_ver_but_committing_with_correct_ver(): void
    {
        $stream = 'should_not_fail_to_commit_if_started_with_wrong_ver_but_committing_with_correct_ver';

        $transaction = $this->connection->startTransaction($stream, 0);

        $this->connection->appendToStream($stream, ExpectedVersion::NoStream, [TestEvent::newTestEvent()]);

        $transaction->write([TestEvent::newTestEvent()]);

        $result = $transaction->commit();

        $this->assertSame(1, $result->nextExpectedVersion());
    }

    /** @test */
    public function should_fail_to_commit_if_started_with_correct_ver_but_on_commit_stream_was_deleted(): void
    {
        $stream = 'should_fail_to_commit_if_started_with_correct_ver_but_on_commit_stream_was_deleted';

        $transaction = $this->connection->startTransaction($stream, ExpectedVersion::NoStream);

        $transaction->write([TestEvent::newTestEvent()]);

        $this->connection->deleteStream($stream, ExpectedVersion::NoStream, true);

        $this->expectException(StreamDeleted::class);

        $transaction->commit();
    }

    /** @test */
    public function idempotency_is_correct_for_explicit_transactions_with_expected_version_any(): void
    {
        $stream = 'idempotency_is_correct_for_explicit_transactions_with_expected_version_any';

        $event = new EventData(null, 'SomethingHappened', true, '{Value:42}');

        $transaction1 = $this->connection->startTransaction($stream, ExpectedVersion::Any);
        $transaction1->write([$event]);
        $result1 = $transaction1->commit();
        $this->assertSame(0, $result1->nextExpectedVersion());

        $transaction2 = $this->connection->startTransaction(
            $stream,
            ExpectedVersion::Any
        );
        $transaction2->write([$event]);
        $result2 = $transaction2->commit();
        $this->assertSame(0, $result2->nextExpectedVersion());

        $result = $this->connection->readStreamEventsForward(
            $stream,
            0,
            100,
            false
        );

        $this->assertCount(1, $result->events());
        $this->assertTrue($event->eventId()->equals($result->events()[0]->event()->eventId()));
    }
}
