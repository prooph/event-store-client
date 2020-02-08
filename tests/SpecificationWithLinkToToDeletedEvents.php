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
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Util\Guid;

trait SpecificationWithLinkToToDeletedEvents
{
    use SpecificationWithConnection;

    protected string $linkedStreamName;
    protected string $deletedStreamName;

    protected function given(): Generator
    {
        $creds = DefaultData::adminCredentials();
        $this->linkedStreamName = Guid::generateAsHex();
        $this->deletedStreamName = Guid::generateAsHex();

        yield $this->conn->appendToStreamAsync(
            $this->deletedStreamName,
            ExpectedVersion::ANY,
            [new EventData(null, 'testing', true, '{"foo":"bar"}')],
            $creds
        );

        yield $this->conn->appendToStreamAsync(
            $this->linkedStreamName,
            ExpectedVersion::ANY,
            [new EventData(null, SystemEventTypes::LINK_TO, false, '0@' . $this->deletedStreamName)],
            $creds
        );

        yield $this->conn->deleteStreamAsync(
            $this->deletedStreamName,
            ExpectedVersion::ANY
        );
    }
}
