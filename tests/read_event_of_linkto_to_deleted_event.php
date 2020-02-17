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
use Prooph\EventStore\EventReadResult;
use Prooph\EventStore\EventReadStatus;
use Throwable;

class read_event_of_linkto_to_deleted_event extends TestCase
{
    use SpecificationWithLinkToToDeletedEvents;

    private EventReadResult $read;

    protected function when(): Generator
    {
        $this->read = yield $this->conn->readEventAsync(
            $this->linkedStreamName,
            0,
            true
        );
    }

    /**
     * @test
     * @throws Throwable
     */
    public function the_linked_event_is_returned(): void
    {
        $this->execute(function () {
            $this->assertNotNull($this->read->event()->link());

            yield new Success();
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function the_deleted_event_is_not_resolved(): void
    {
        $this->execute(function () {
            $this->assertNull($this->read->event()->event());

            yield new Success();
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function the_status_is_success(): void
    {
        $this->execute(function () {
            $this->assertTrue(EventReadStatus::success()->equals($this->read->status()));

            yield new Success();
        });
    }
}
