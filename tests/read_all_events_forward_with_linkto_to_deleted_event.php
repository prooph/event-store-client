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

use Amp\PHPUnit\AsyncTestCase;
use Amp\Success;
use Generator;
use Prooph\EventStore\StreamEventsSlice;

class read_all_events_forward_with_linkto_to_deleted_event extends AsyncTestCase
{
    use SpecificationWithLinkToToDeletedEvents;

    private StreamEventsSlice $read;

    protected function when(): Generator
    {
        $this->read = yield $this->connection->readStreamEventsForwardAsync(
            $this->linkedStreamName,
            0,
            1
        );
    }

    /** @test */
    public function one_event_is_read(): Generator
    {
        yield $this->execute(function (): Generator {
            $this->assertCount(1, $this->read->events());

            yield new Success();
        });
    }

    /** @test */
    public function the_linked_event_is_not_resolved(): Generator
    {
        yield $this->execute(function (): Generator {
            $this->assertNull($this->read->events()[0]->event());

            yield new Success();
        });
    }

    /** @test */
    public function the_link_event_is_included(): Generator
    {
        yield $this->execute(function (): Generator {
            $this->assertNotNull($this->read->events()[0]->originalEvent());

            yield new Success();
        });
    }

    /** @test */
    public function the_event_is_not_resolved(): Generator
    {
        yield $this->execute(function (): Generator {
            $this->assertFalse($this->read->events()[0]->isResolved());

            yield new Success();
        });
    }
}
