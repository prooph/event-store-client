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

use Amp\Success;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\StreamEventsSlice;
use Throwable;

class read_all_events_backward_with_linkto_deleted_event extends TestCase
{
    use SpecificationWithLinkToToDeletedEvents;

    private StreamEventsSlice $read;

    protected function when(): Generator
    {
        $this->read = yield $this->conn->readStreamEventsBackwardAsync(
            $this->linkedStreamName,
            0,
            1
        );
    }

    /**
     * @test
     * @throws Throwable
     */
    public function one_event_is_read(): void
    {
        $this->execute(function () {
            $this->assertCount(1, $this->read->events());

            yield new Success();
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function the_linked_event_is_not_resolved(): void
    {
        $this->execute(function () {
            $this->assertNull($this->read->events()[0]->event());

            yield new Success();
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function the_link_event_is_included(): void
    {
        $this->execute(function () {
            $this->assertNotNull($this->read->events()[0]->originalEvent());

            yield new Success();
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function the_event_is_not_resolved(): void
    {
        $this->execute(function () {
            $this->assertFalse($this->read->events()[0]->isResolved());

            yield new Success();
        });
    }
}
