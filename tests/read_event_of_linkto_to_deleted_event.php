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
use Prooph\EventStore\EventReadResult;
use Prooph\EventStore\EventReadStatus;

class read_event_of_linkto_to_deleted_event extends AsyncTestCase
{
    use SpecificationWithLinkToToDeletedEvents;

    private EventReadResult $read;

    protected function when(): void
    {
        $this->read = $this->connection->readEvent(
            $this->linkedStreamName,
            0,
            true
        );
    }

    /** @test */
    public function the_linked_event_is_returned(): void
    {
        $this->execute(function (): void {
            $this->assertNotNull($this->read->event()->link());
        });
    }

    /** @test */
    public function the_deleted_event_is_not_resolved(): void
    {
        $this->execute(function (): void {
            $this->assertNull($this->read->event()->event());
        });
    }

    /** @test */
    public function the_status_is_success(): void
    {
        $this->execute(function (): void {
            $this->assertSame(EventReadStatus::Success, $this->read->status());
        });
    }
}
