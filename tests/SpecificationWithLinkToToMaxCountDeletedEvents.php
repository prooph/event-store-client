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
use Prooph\EventStore\Common\SystemEventTypes;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\Util\Guid;

trait SpecificationWithLinkToToMaxCountDeletedEvents
{
    use SpecificationWithConnection;

    protected string $linkedStreamName;
    protected string $deletedStreamName;

    protected function given(): Generator
    {
        $creds = DefaultData::adminCredentials();

        $this->deletedStreamName = Guid::generateAsHex();
        $this->linkedStreamName = Guid::generateAsHex();

        yield $this->conn->appendToStreamAsync(
            $this->deletedStreamName,
            ExpectedVersion::ANY,
            [
                new EventData(EventId::generate(), 'testing1', true, \json_encode(['foo' => 4])),
            ],
            $creds
        );

        yield $this->conn->setStreamMetadataAsync(
            $this->deletedStreamName,
            ExpectedVersion::ANY,
            new StreamMetadata(2)
        );

        yield $this->conn->appendToStreamAsync(
            $this->deletedStreamName,
            ExpectedVersion::ANY,
            [
                new EventData(EventId::generate(), 'testing2', true, \json_encode(['foo' => 4])),
            ]
        );

        yield $this->conn->appendToStreamAsync(
            $this->deletedStreamName,
            ExpectedVersion::ANY,
            [
                new EventData(EventId::generate(), 'testing3', true, \json_encode(['foo' => 4])),
            ]
        );

        yield $this->conn->appendToStreamAsync(
            $this->linkedStreamName,
            ExpectedVersion::ANY,
            [
                new EventData(EventId::generate(), SystemEventTypes::LINK_TO, false, '0@' . $this->deletedStreamName),
            ],
            $creds
        );
    }
}
