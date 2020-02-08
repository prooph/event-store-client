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

use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Common\SystemEventTypes;
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\EventData;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\StreamMetadata;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;

class read_stream_events_with_unresolved_linkto extends TestCase
{
    use SpecificationWithConnection;

    /** @var EventData[] */
    private array $testEvents;
    private string $stream;
    private string $links;

    protected function when(): Generator
    {
        yield $this->conn->setStreamMetadataAsync(
            '$all',
            ExpectedVersion::ANY,
            StreamMetadata::create()->setReadRoles(SystemRoles::ALL)->build(),
            DefaultData::adminCredentials()
        );

        $this->testEvents = TestEvent::newAmount(20);

        yield $this->conn->appendToStreamAsync(
            $this->stream,
            ExpectedVersion::NO_STREAM,
            $this->testEvents
        );

        yield $this->conn->appendToStreamAsync(
            $this->links,
            ExpectedVersion::NO_STREAM,
            [new EventData(null, SystemEventTypes::LINK_TO, false, '0@read_stream_events_with_unresolved_linkto')]
        );

        yield $this->conn->deleteStreamAsync($this->stream, ExpectedVersion::ANY);
    }

    /**
     * @test
     * @throws Throwable
     */
    public function ensure_deleted_stream(): void
    {
        $this->stream = 'read_stream_events_with_unresolved_linkto_1';
        $this->links = 'read_stream_events_with_unresolved_linkto_links_1';

        $this->execute(function () {
            $res = yield $this->conn->readStreamEventsForwardAsync(
                $this->stream,
                0,
                100,
                false
            );
            \assert($res instanceof StreamEventsSlice);

            $this->assertTrue(SliceReadStatus::streamNotFound()->equals($res->status()));
            $this->assertCount(0, $res->events());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function returns_unresolved_linkto(): void
    {
        $this->stream = 'read_stream_events_with_unresolved_linkto_2';
        $this->links = 'read_stream_events_with_unresolved_linkto_links_2';

        $this->execute(function () {
            $read = yield $this->conn->readStreamEventsForwardAsync(
                $this->links,
                0,
                1,
                true
            );
            \assert($read instanceof StreamEventsSlice);

            $this->assertCount(1, $read->events());
            $this->assertNull($read->events()[0]->event());
            $this->assertNotNull($read->events()[0]->link());
        });
    }
}
