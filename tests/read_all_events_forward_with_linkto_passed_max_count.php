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

class read_all_events_forward_with_linkto_passed_max_count extends TestCase
{
    use SpecificationWithLinkToToMaxCountDeletedEvents;

    private StreamEventsSlice $read;

    protected function when(): Generator
    {
        $this->read = yield $this->conn->readStreamEventsForwardAsync($this->linkedStreamName, 0, 1, true);
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
}
