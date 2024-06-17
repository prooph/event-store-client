<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2024 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2024 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\PHPUnit\AsyncTestCase;
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
use ProophTest\EventStoreClient\Helper\EventDataComparer;
use ProophTest\EventStoreClient\Helper\TestEvent;

class read_all_events_forward_should extends AsyncTestCase
{
    use SpecificationWithConnection;

    /** @var EventData[] */
    private array $testEvents;

    private Position $from;

    private string $stream;

    protected function when(): void
    {
        $this->connection->setStreamMetadata(
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
                    []
                )
            ),
            DefaultData::adminCredentials()
        );

        $result = $this->connection->readAllEventsBackward(Position::end(), 1, false);

        $this->from = $result->nextPosition();
        $this->testEvents = TestEvent::newAmount(20);
        $this->stream = 'read_all_events_forward_should-' . Guid::generateAsHex();

        $this->connection->appendToStream($this->stream, ExpectedVersion::NoStream, $this->testEvents);
    }

    protected function end(): void
    {
        $this->connection->setStreamMetadata(
            '$all',
            ExpectedVersion::Any,
            new StreamMetadata(),
            DefaultData::adminCredentials()
        );

        $this->connection->close();
    }

    /** @test */
    public function return_empty_slice_if_asked_to_read_from_end(): void
    {
        $this->execute(function (): void {
            $read = $this->connection->readAllEventsForward(Position::end(), 1, false);

            $this->assertTrue($read->isEndOfStream());
            $this->assertCount(0, $read->events());
        });
    }

    /** @test */
    public function return_events_in_same_order_as_written(): void
    {
        $this->execute(function (): void {
            $read = $this->connection->readAllEventsForward($this->from, \count($this->testEvents) + 10, false);

            $events = \array_map(
                fn (ResolvedEvent $e): RecordedEvent => $e->event(),
                \array_slice($read->events(), \count($read->events()) - \count($this->testEvents))
            );

            $this->assertTrue(EventDataComparer::allEqual($this->testEvents, $events));
            $this->assertSame($read->readDirection(), ReadDirection::Forward);
        });
    }

    /** @test */
    public function be_able_to_read_all_one_by_one_until_end_of_stream(): void
    {
        $this->execute(function (): void {
            $all = [];
            $position = $this->from;
            $slice = null;

            while (true) {
                $slice = $this->connection->readAllEventsForward($position, 1, false);

                if ($slice->isEndOfStream()) {
                    break;
                }

                $all[] = $slice->events()[0]->event();
                $position = $slice->nextPosition();
            }

            $events = \array_slice($all, \count($all) - \count($this->testEvents));

            $this->assertTrue(EventDataComparer::allEqual($this->testEvents, $events));
            $this->assertTrue($slice->fromPosition()->equals($position));
        });
    }

    /** @test */
    public function be_able_to_read_events_slice_at_time(): void
    {
        $this->execute(function (): void {
            $all = [];
            $position = $this->from;

            do {
                $slice = $this->connection->readAllEventsForward($position, 5, false);

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
        $this->execute(function (): void {
            $read = $this->connection->readAllEventsForward($this->from, 30, false);

            $this->assertLessThan(30, \count($read->events()));

            $events = \array_map(
                fn (ResolvedEvent $e): RecordedEvent => $e->event(),
                \array_slice($read->events(), \count($read->events()) - \count($this->testEvents))
            );

            $this->assertTrue(EventDataComparer::allEqual($this->testEvents, $events));
        });
    }

    /** @test */
    public function throw_when_got_int_max_value_as_maxcount(): void
    {
        $this->execute(function (): void {
            $this->expectException(InvalidArgumentException::class);

            $this->connection->readAllEventsForward(Position::start(), \PHP_INT_MAX, false);
        });
    }
}
