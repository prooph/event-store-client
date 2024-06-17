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

class read_all_events_backward_should extends AsyncTestCase
{
    use SpecificationWithConnection;

    /** @var EventData[] */
    private array $testEvents;

    private Position $endOfEvents;

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

        $this->testEvents = TestEvent::newAmount(20);

        $this->connection->appendToStream('stream-' . Guid::generateAsHex(), ExpectedVersion::NoStream, $this->testEvents);

        $result = $this->connection->appendToStream('stream-' . Guid::generateAsHex(), ExpectedVersion::NoStream, $this->testEvents);

        $lastId = $this->testEvents[19]->eventId();
        $this->endOfEvents = $result->logPosition();

        do {
            $slice = $this->connection->readAllEventsBackward($this->endOfEvents, 1, false);

            if ($slice->events()[0]->event()->eventId()->equals($lastId)) {
                break;
            }

            $this->endOfEvents = $slice->nextPosition();
        } while (true);
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
    public function return_empty_slice_if_asked_to_read_from_start(): void
    {
        $this->execute(function (): void {
            $read = $this->connection->readAllEventsBackward(Position::start(), 1, false);

            $this->assertTrue($read->isEndOfStream());
            $this->assertCount(0, $read->events());
        });
    }

    /** @test */
    public function return_events_in_reversed_order_compared_to_written(): void
    {
        $this->execute(function (): void {
            $read = $this->connection->readAllEventsBackward($this->endOfEvents, \count($this->testEvents), false);

            $readEvents = \array_map(
                fn (ResolvedEvent $resolvedEvent): RecordedEvent => $resolvedEvent->event(),
                \array_slice($read->events(), 0, \count($this->testEvents))
            );

            $this->assertTrue(EventDataComparer::allEqual(
                \array_reverse($this->testEvents),
                $readEvents
            ));

            $this->assertSame(ReadDirection::Backward, $read->readDirection());
            $this->assertTrue($read->fromPosition()->equals($this->endOfEvents));
        });
    }

    /** @test */
    public function be_able_to_read_all_one_by_one_until_end_of_stream(): void
    {
        $this->execute(function (): void {
            $all = [];
            $position = $this->endOfEvents;

            while (true) {
                $slice = $this->connection->readAllEventsBackward($position, 1, false);

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

    /** @test */
    public function be_able_to_read_events_slice_at_time(): void
    {
        $this->execute(function (): void {
            $all = [];
            $position = $this->endOfEvents;

            do {
                $slice = $this->connection->readAllEventsBackward($position, 5, false);

                foreach ($slice->events() as $event) {
                    $all[] = $event->event();
                }

                $position = $slice->nextPosition();
            } while (! $slice->isEndOfStream());

            $events = \array_slice($all, 0, \count($this->testEvents));

            $this->assertTrue(EventDataComparer::allEqual(\array_reverse($this->testEvents), $events));
        });
    }

    /** @test */
    public function throw_when_got_int_max_value_as_maxcount(): void
    {
        $this->execute(function (): void {
            $this->expectException(InvalidArgumentException::class);

            $this->connection->readAllEventsBackward(Position::start(), \PHP_INT_MAX, false);
        });
    }
}
