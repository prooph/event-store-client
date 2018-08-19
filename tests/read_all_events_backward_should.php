<?php
/**
 * This file is part of the prooph/event-store-client.
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
use Prooph\EventStoreClient\AllEventsSlice;
use Prooph\EventStoreClient\Common\SystemRoles;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\Internal\UuidGenerator;
use Prooph\EventStoreClient\Position;
use Prooph\EventStoreClient\RecordedEvent;
use Prooph\EventStoreClient\ResolvedEvent;
use Prooph\EventStoreClient\StreamAcl;
use Prooph\EventStoreClient\StreamMetadata;
use Prooph\EventStoreClient\WriteResult;
use ProophTest\EventStoreClient\Helper\EventDataComparer;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;

class read_all_events_backward_should extends TestCase
{
    use SpecificationWithConnection;

    /** @var EventData[] */
    private $testEvents;
    /** @var Position */
    private $endOfEvents;
    /** @var string */
    private $stream;

    protected function when(): Generator
    {
        yield $this->conn->setStreamMetadataAsync(
            '$all',
            ExpectedVersion::Any,
            new StreamMetadata(
                null,
                null,
                null,
                null,
                new StreamAcl(
                    [SystemRoles::All],
                    [],
                    [],
                    [],
                    [],
                    []
                )
            ),
            DefaultData::adminCredentials()
        );

        $this->testEvents = TestEvent::newAmount(20);

        yield $this->conn->appendToStreamAsync('stream-' . UuidGenerator::generate(), ExpectedVersion::EmptyStream, $this->testEvents);

        /** @var WriteResult $result */
        $result = yield $this->conn->appendToStreamAsync('stream-' . UuidGenerator::generate(), ExpectedVersion::NoStream, $this->testEvents);

        $lastId = $this->testEvents[19]->eventId();
        $this->endOfEvents = $result->logPosition();

        do {
            /** @var AllEventsSlice $slice */
            $slice = yield $this->conn->readAllEventsBackwardAsync($this->endOfEvents, 1, false);

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
            ExpectedVersion::Any,
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
        $this->executeCallback(function () {
            /** @var AllEventsSlice $read */
            $read = yield $this->conn->readAllEventsBackwardAsync(Position::start(), 1, false);

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
        $this->executeCallback(function () {
            /** @var AllEventsSlice $read */
            $read = yield $this->conn->readAllEventsBackwardAsync($this->endOfEvents, \count($this->testEvents), false);

            $readEvents = \array_map(
                function (ResolvedEvent $resolvedEvent): RecordedEvent {
                    return $resolvedEvent->event();
                },
                \array_slice($read->events(), 0, \count($this->testEvents))
            );

            $this->assertTrue(EventDataComparer::allEqual(
                \array_reverse($this->testEvents),
                $readEvents
            ));
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function be_able_to_read_all_one_by_one_until_end_of_stream(): void
    {
        $this->executeCallback(function () {
            $all = [];
            $position = $this->endOfEvents;

            while (true) {
                /** @var AllEventsSlice $slice */
                $slice = yield $this->conn->readAllEventsBackwardAsync($position, 1, false);

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
        $this->executeCallback(function () {
            $all = [];
            $position = $this->endOfEvents;

            do {
                /** @var AllEventsSlice $slice */
                $slice = yield $this->conn->readAllEventsBackwardAsync($position, 5, false);

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
        $this->executeCallback(function () {
            $this->expectException(InvalidArgumentException::class);

            yield $this->conn->readAllEventsBackwardAsync(Position::start(), \PHP_INT_MAX, false);
        });
    }
}
