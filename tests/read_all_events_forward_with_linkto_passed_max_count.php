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

use Amp\PHPUnit\AsyncTestCase;
use Prooph\EventStore\StreamEventsSlice;

class read_all_events_forward_with_linkto_passed_max_count extends AsyncTestCase
{
    use SpecificationWithLinkToToMaxCountDeletedEvents;

    private StreamEventsSlice $read;

    protected function when(): void
    {
        $this->read = $this->connection->readStreamEventsForward($this->linkedStreamName, 0, 1, true);
    }

    /** @test */
    public function one_event_is_read(): void
    {
        $this->execute(function (): void {
            $this->assertCount(1, $this->read->events());
        });
    }
}
