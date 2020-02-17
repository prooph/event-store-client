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
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\RecordedEvent;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\StreamPosition;
use ProophTest\EventStoreClient\Helper\EventDataComparer;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;

class read_event_stream_backward_should extends TestCase
{
    /**
     * @test
     * @throws Throwable
     */
    public function throw_if_count_le_zero(): void
    {
        wait(call(function () {
            $stream = 'read_event_stream_backward_should_throw_if_count_le_zero';

            $store = TestConnection::create();
            yield $store->connectAsync();

            $this->expectException(InvalidArgumentException::class);

            $store->readStreamEventsBackwardAsync(
                $stream,
                0,
                0,
                false
            );
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function notify_using_status_code_if_stream_not_found(): void
    {
        wait(call(function () {
            $stream = 'read_event_stream_backward_should_notify_using_status_code_if_stream_not_found';

            $store = TestConnection::create();
            yield $store->connectAsync();

            $read = yield $store->readStreamEventsBackwardAsync(
                $stream,
                StreamPosition::END,
                1,
                false
            );
            \assert($read instanceof StreamEventsSlice);

            $this->assertTrue(SliceReadStatus::streamNotFound()->equals($read->status()));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function notify_using_status_code_if_stream_was_deleted(): void
    {
        wait(call(function () {
            $stream = 'read_event_stream_backward_should_notify_using_status_code_if_stream_was_deleted';

            $store = TestConnection::create();
            yield $store->connectAsync();
            yield $store->deleteStreamAsync($stream, ExpectedVersion::NO_STREAM, true);

            $read = yield $store->readStreamEventsBackwardAsync(
                $stream,
                StreamPosition::END,
                1,
                false
            );
            \assert($read instanceof StreamEventsSlice);

            $this->assertTrue(SliceReadStatus::streamDeleted()->equals($read->status()));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function return_no_events_when_called_on_empty_stream(): void
    {
        wait(call(function () {
            $stream = 'read_event_stream_backward_should_return_single_event_when_called_on_empty_stream';

            $store = TestConnection::create();
            yield $store->connectAsync();

            $read = yield $store->readStreamEventsBackwardAsync(
                $stream,
                StreamPosition::END,
                1,
                false
            );
            \assert($read instanceof StreamEventsSlice);

            $this->assertCount(0, $read->events());
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function return_partial_slice_if_no_enough_events_in_stream(): void
    {
        wait(call(function () {
            $stream = 'read_event_stream_backward_should_return_partial_slice_if_no_enough_events_in_stream';

            $store = TestConnection::create();
            yield $store->connectAsync();

            $testEvents = TestEvent::newAmount(10);
            yield $store->appendToStreamAsync(
                $stream,
                ExpectedVersion::NO_STREAM,
                $testEvents
            );

            $read = yield $store->readStreamEventsBackwardAsync(
                $stream,
                1,
                5,
                false
            );
            \assert($read instanceof StreamEventsSlice);

            $this->assertCount(2, $read->events());
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function return_events_reversed_compared_to_written(): void
    {
        wait(call(function () {
            $stream = 'read_event_stream_backward_should_return_events_reversed_compared_to_written';

            $store = TestConnection::create();
            yield $store->connectAsync();

            $testEvents = TestEvent::newAmount(10);
            yield $store->appendToStreamAsync(
                $stream,
                ExpectedVersion::NO_STREAM,
                $testEvents
            );

            $read = yield $store->readStreamEventsBackwardAsync(
                $stream,
                StreamPosition::END,
                10,
                false
            );
            \assert($read instanceof StreamEventsSlice);

            $events = \array_map(
                fn (ResolvedEvent $e): RecordedEvent => $e->event(),
                \array_reverse($read->events())
            );

            $this->assertTrue(EventDataComparer::allEqual($testEvents, $events));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function be_able_to_read_single_event_from_arbitrary_position(): void
    {
        wait(call(function () {
            $stream = 'read_event_stream_backward_should_be_able_to_read_single_event_from_arbitrary_position';

            $store = TestConnection::create();
            yield $store->connectAsync();

            $testEvents = TestEvent::newAmount(10);
            yield $store->appendToStreamAsync(
                $stream,
                ExpectedVersion::NO_STREAM,
                $testEvents
            );

            $read = yield $store->readStreamEventsBackwardAsync(
                $stream,
                7,
                1,
                false
            );
            \assert($read instanceof StreamEventsSlice);

            $this->assertTrue(EventDataComparer::equal($testEvents[7], $read->events()[0]->event()));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function be_able_to_read_first_event(): void
    {
        wait(call(function () {
            $stream = 'read_event_stream_backward_should_be_able_to_read_first_event';

            $store = TestConnection::create();
            yield $store->connectAsync();

            $testEvents = TestEvent::newAmount(10);
            yield $store->appendToStreamAsync(
                $stream,
                ExpectedVersion::NO_STREAM,
                $testEvents
            );

            $read = yield $store->readStreamEventsBackwardAsync(
                $stream,
                StreamPosition::START,
                1,
                false
            );
            \assert($read instanceof StreamEventsSlice);

            $this->assertCount(1, $read->events());
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function be_able_to_read_last_event(): void
    {
        wait(call(function () {
            $stream = 'read_event_stream_backward_should_be_able_to_read_last_event';

            $store = TestConnection::create();
            yield $store->connectAsync();

            $testEvents = TestEvent::newAmount(10);
            yield $store->appendToStreamAsync(
                $stream,
                ExpectedVersion::NO_STREAM,
                $testEvents
            );

            $read = yield $store->readStreamEventsBackwardAsync(
                $stream,
                StreamPosition::END,
                1,
                false
            );
            \assert($read instanceof StreamEventsSlice);

            $this->assertTrue(EventDataComparer::equal($testEvents[9], $read->events()[0]->event()));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function be_able_to_read_slice_from_arbitrary_position(): void
    {
        wait(call(function () {
            $stream = 'read_event_stream_backward_should_be_able_to_read_slice_from_arbitrary_position';

            $store = TestConnection::create();
            yield $store->connectAsync();

            $testEvents = TestEvent::newAmount(10);
            yield $store->appendToStreamAsync(
                $stream,
                ExpectedVersion::NO_STREAM,
                $testEvents
            );

            $read = yield $store->readStreamEventsBackwardAsync(
                $stream,
                3,
                2,
                false
            );
            \assert($read instanceof StreamEventsSlice);

            $events = \array_map(
                fn (ResolvedEvent $e): RecordedEvent => $e->event(),
                $read->events()
            );

            $this->assertTrue(EventDataComparer::allEqual(
                \array_reverse(\array_slice($testEvents, 2, 2)),
                $events
            ));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function throw_when_got_int_max_value_as_maxcount(): void
    {
        wait(call(function () {
            $store = TestConnection::create();
            yield $store->connectAsync();

            $this->expectException(InvalidArgumentException::class);

            yield $store->readStreamEventsBackwardAsync(
                'foo',
                StreamPosition::START,
                \PHP_INT_MAX,
                false
            );
        }));
    }
}
