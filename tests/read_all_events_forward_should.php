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
use ProophTest\EventStoreClient\Helper\EventDataComparer;
use ProophTest\EventStoreClient\Helper\TestEvent;

class read_all_events_forward_should extends TestCase
{
    use SpecificationWithConnection;

    /** @var EventData[] */
    private $testEvents;
    /** @var Position */
    private $from;
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

        /** @var AllEventsSlice $result */
        $result = yield $this->conn->readAllEventsBackwardAsync(Position::end(), 1, false);

        $this->from = $result->nextPosition();
        $this->testEvents = TestEvent::newAmount(20);
        $this->stream = 'read_all_events_forward_should-' . UuidGenerator::generate();

        yield $this->conn->appendToStreamAsync($this->stream, ExpectedVersion::EmptyStream, $this->testEvents);
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

    /** @test */
    public function return_empty_slice_if_asked_to_read_from_end(): void
    {
        $this->executeCallback(function () {
            /** @var AllEventsSlice $read */
            $read = yield $this->conn->readAllEventsForwardAsync(Position::end(), 1, false);

            $this->assertTrue($read->isEndOfStream());
            $this->assertCount(0, $read->events());
        });
    }

    /** @test */
    public function return_events_in_same_order_as_written(): void
    {
        $this->executeCallback(function () {
            /** @var AllEventsSlice $read */
            $read = yield $this->conn->readAllEventsForwardAsync($this->from, \count($this->testEvents) + 10, false);

            $events = \array_map(
                function (ResolvedEvent $e): RecordedEvent {
                    return $e->event();
                },
                \array_slice($read->events(), \count($read->events()) - \count($this->testEvents))
            );

            $this->assertTrue(EventDataComparer::allEqual($this->testEvents, $events));
        });
    }

    /** @test */
    public function be_able_to_read_all_one_by_one_until_end_of_stream(): void
    {
        $this->executeCallback(function () {
            $all = [];
            $position = $this->from;

            while (true) {
                /** @var AllEventsSlice $slice */
                $slice = yield $this->conn->readAllEventsForwardAsync($position, 1, false);

                if ($slice->isEndOfStream()) {
                    break;
                }

                $all[] = $slice->events()[0]->event();
                $position = $slice->nextPosition();
            }

            $events = \array_slice($all, \count($all) - \count($this->testEvents));

            $this->assertTrue(EventDataComparer::allEqual($this->testEvents, $events));
        });
    }

    /** @test */
    public function be_able_to_read_events_slice_at_time(): void
    {
        $this->executeCallback(function () {
            $all = [];
            $position = $this->from;

            do {
                /** @var AllEventsSlice $slice */
                $slice = yield $this->conn->readAllEventsForwardAsync($position, 5, false);

                foreach ($slice->events() as $event) {
                    $all[] = $event->event();
                }

                $position = $slice->nextPosition();
            } while (! $slice->isEndOfStream());

            $events = \array_slice($all, \count($all) - \count($this->testEvents));

            $this->assertTrue(EventDataComparer::allEqual($this->testEvents, $events));
        });
    }

    /** @test */
    public function return_partial_slice_if_not_enough_events(): void
    {
        $this->executeCallback(function () {
            /** @var AllEventsSlice $read */
            $read = yield $this->conn->readAllEventsForwardAsync($this->from, 30, false);

            $this->assertLessThan(30, \count($read->events()));

            $events = \array_map(
                function (ResolvedEvent $e): RecordedEvent {
                    return $e->event();
                },
                \array_slice($read->events(), \count($read->events()) - \count($this->testEvents))
            );

            $this->assertTrue(EventDataComparer::allEqual($this->testEvents, $events));
        });
    }

    /** @test */
    public function throw_when_got_int_max_value_as_maxcount(): void
    {
        $this->executeCallback(function () {
            $this->expectException(InvalidArgumentException::class);

            yield $this->conn->readAllEventsForwardAsync(Position::start(), \PHP_INT_MAX, false);
        });
    }
}
