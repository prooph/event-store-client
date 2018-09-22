<?php
/**
 * This file is part of the prooph/event-store-client.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\Common\SystemEventTypes;
use Prooph\EventStoreClient\Common\SystemRoles;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\RecordedEvent;
use Prooph\EventStoreClient\ResolvedEvent;
use Prooph\EventStoreClient\SliceReadStatus;
use Prooph\EventStoreClient\StreamEventsSlice;
use Prooph\EventStoreClient\StreamMetadata;
use Prooph\EventStoreClient\StreamPosition;
use ProophTest\EventStoreClient\Helper\EventDataComparer;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;
use function Amp\call;
use function Amp\Promise\wait;

class read_stream_events_with_unresolved_linkto extends TestCase
{
    use SpecificationWithConnection;

    /** @var EventData[] */
    private $testEvents;
    /** @var string */
    private $stream;
    /** @var string */
    private $links;

    protected function when(): Generator
    {
        yield $this->conn->setStreamMetadataAsync(
            '$all',
            ExpectedVersion::ANY,
            StreamMetadata::build()->setReadRoles(SystemRoles::ALL)->build(),
            DefaultData::adminCredentials()
        );

        $this->testEvents = TestEvent::newAmount(20);

        yield $this->conn->appendToStreamAsync(
            $this->stream,
            ExpectedVersion::EMPTY_STREAM,
            $this->testEvents
        );

        yield $this->conn->appendToStreamAsync(
            $this->links,
            ExpectedVersion::EMPTY_STREAM,
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

        $this->executeCallback(function () {
            /** @var StreamEventsSlice $res */
            $res = yield $this->conn->readStreamEventsForwardAsync(
                $this->stream,
                0,
                100,
                false
            );

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

        $this->executeCallback(function () {
            /** @var StreamEventsSlice $read */
            $read = yield $this->conn->readStreamEventsForwardAsync(
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
