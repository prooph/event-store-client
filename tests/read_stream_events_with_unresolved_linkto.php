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
use Prooph\EventStore\Common\SystemEventTypes;
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\EventData;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamMetadata;
use ProophTest\EventStoreClient\Helper\TestEvent;

class read_stream_events_with_unresolved_linkto extends AsyncTestCase
{
    use SpecificationWithConnection;

    /** @var EventData[] */
    private array $testEvents;

    private string $stream;

    private string $links;

    protected function when(): void
    {
        $this->connection->setStreamMetadata(
            '$all',
            ExpectedVersion::Any,
            StreamMetadata::create()->setReadRoles(SystemRoles::All)->build(),
            DefaultData::adminCredentials()
        );

        $this->testEvents = TestEvent::newAmount(20);

        $this->connection->appendToStream(
            $this->stream,
            ExpectedVersion::NoStream,
            $this->testEvents
        );

        $this->connection->appendToStream(
            $this->links,
            ExpectedVersion::NoStream,
            [new EventData(null, SystemEventTypes::LinkTo->value, false, '0@read_stream_events_with_unresolved_linkto')]
        );

        $this->connection->deleteStream($this->stream, ExpectedVersion::Any);
    }

    /** @test */
    public function ensure_deleted_stream(): void
    {
        $this->stream = 'read_stream_events_with_unresolved_linkto_1';
        $this->links = 'read_stream_events_with_unresolved_linkto_links_1';

        $this->execute(function (): void {
            $res = $this->connection->readStreamEventsForward(
                $this->stream,
                0,
                100,
                false
            );

            $this->assertSame(SliceReadStatus::StreamNotFound, $res->status());
            $this->assertCount(0, $res->events());
        });
    }

    /** @test */
    public function returns_unresolved_linkto(): void
    {
        $this->stream = 'read_stream_events_with_unresolved_linkto_2';
        $this->links = 'read_stream_events_with_unresolved_linkto_links_2';

        $this->execute(function (): void {
            $read = $this->connection->readStreamEventsForward(
                $this->links,
                0,
                1,
                true
            );

            $this->assertCount(1, $read->events());
            $this->assertNull($read->events()[0]->event());
            $this->assertNotNull($read->events()[0]->link());
        });
    }
}
