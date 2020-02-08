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

use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\AllEventsSlice;
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\EventData;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Position;
use Prooph\EventStore\ReadDirection;
use Prooph\EventStore\RecordedEvent;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\StreamAcl;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\Util\Guid;
use Prooph\EventStore\WriteResult;
use ProophTest\EventStoreClient\Helper\EventDataComparer;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;

class read_all_events_backward_should extends TestCase
{
    use SpecificationWithConnection;

    /** @var EventData[] */
    private array $testEvents;
    private Position $endOfEvents;
    private string $stream;

    protected function when(): Generator
    {
        yield $this->conn->setStreamMetadataAsync(
            '$all',
            ExpectedVersion::ANY,
            new StreamMetadata(
                null,
                null,
                null,
                null,
                new StreamAcl(
                    [SystemRoles::ALL],
                    [],
                    [],
                    [],
                    []
                )
            ),
            DefaultData::adminCredentials()
        );

        $this->testEvents = TestEvent::newAmount(20);

        yield $this->conn->appendToStreamAsync('stream-' . Guid::generateAsHex(), ExpectedVersion::NO_STREAM, $this->testEvents);

        $result = yield $this->conn->appendToStreamAsync('stream-' . Guid::generateAsHex(), ExpectedVersion::NO_STREAM, $this->testEvents);
        \assert($result instanceof WriteResult);

        $lastId = $this->testEvents[19]->eventId();
        $this->endOfEvents = $result->logPosition();

        do {
            $slice = yield $this->conn->readAllEventsBackwardAsync($this->endOfEvents, 1, false);
            \assert($slice instanceof AllEventsSlice);

            if ($slice->events()[0]->event()->eventId()->equals($lastId)) {
                break;
            }

            $this->endOfEvents = $slice->nextPosition();
        } while (true);
    }

    protected function end(): Generator
    {
        yield $this->conn->setStreamMetadataAsync(
            '$all',
            ExpectedVersion::ANY,
            new StreamMetadata(),
            DefaultData::adminCredentials()
        );

        $this->conn->close();
    }

    /**
     * @test
     * @throws Throwable
     */
    public function return_empty_slice_if_asked_to_read_from_start(): void
    {
        $this->execute(function () {
            $read = yield $this->conn->readAllEventsBackwardAsync(Position::start(), 1, false);
            \assert($read instanceof AllEventsSlice);

            $this->assertTrue($read->isEndOfStream());
            $this->assertCount(0, $read->events());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function return_events_in_reversed_order_compared_to_written(): void
    {
        $this->execute(function () {
            $read = yield $this->conn->readAllEventsBackwardAsync($this->endOfEvents, \count($this->testEvents), false);
            \assert($read instanceof AllEventsSlice);

            $readEvents = \array_map(
                fn (ResolvedEvent $resolvedEvent): RecordedEvent => $resolvedEvent->event(),
                \array_slice($read->events(), 0, \count($this->testEvents))
            );

            $this->assertTrue(EventDataComparer::allEqual(
                \array_reverse($this->testEvents),
                $readEvents
            ));

            $this->assertTrue($read->readDirection()->equals(ReadDirection::backward()));
            $this->assertTrue($read->fromPosition()->equals($this->endOfEvents));
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function be_able_to_read_all_one_by_one_until_end_of_stream(): void
    {
        $this->execute(function () {
            $all = [];
            $position = $this->endOfEvents;

            while (true) {
                $slice = yield $this->conn->readAllEventsBackwardAsync($position, 1, false);
                \assert($slice instanceof AllEventsSlice);

                if ($slice->isEndOfStream()) {
                    break;
                }

                $all[] = $slice->events()[0]->event();
                $position = $slice->nextPosition();
            }

            $events = \array_slice($all, 0, \count($this->testEvents));

            $this->assertTrue(EventDataComparer::allEqual(\array_reverse($this->testEvents), $events));
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function be_able_to_read_events_slice_at_time(): void
    {
        $this->execute(function () {
            $all = [];
            $position = $this->endOfEvents;

            do {
                $slice = yield $this->conn->readAllEventsBackwardAsync($position, 5, false);
                \assert($slice instanceof AllEventsSlice);

                foreach ($slice->events() as $event) {
                    $all[] = $event->event();
                }

                $position = $slice->nextPosition();
            } while (! $slice->isEndOfStream());

            $events = \array_slice($all, 0, \count($this->testEvents));

            $this->assertTrue(EventDataComparer::allEqual(\array_reverse($this->testEvents), $events));
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function throw_when_got_int_max_value_as_maxcount(): void
    {
        $this->execute(function () {
            $this->expectException(InvalidArgumentException::class);

            yield $this->conn->readAllEventsBackwardAsync(Position::start(), \PHP_INT_MAX, false);
        });
    }
}
