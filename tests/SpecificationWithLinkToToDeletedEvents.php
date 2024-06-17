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

use Prooph\EventStore\Common\SystemEventTypes;
use Prooph\EventStore\EventData;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Util\Guid;

trait SpecificationWithLinkToToDeletedEvents
{
    use SpecificationWithConnection;

    protected string $linkedStreamName;

    protected string $deletedStreamName;

    protected function given(): void
    {
        $creds = DefaultData::adminCredentials();
        $this->linkedStreamName = Guid::generateAsHex();
        $this->deletedStreamName = Guid::generateAsHex();

        $this->connection->appendToStream(
            $this->deletedStreamName,
            ExpectedVersion::Any,
            [new EventData(null, 'testing', true, '{"foo":"bar"}')],
            $creds
        );

        $this->connection->appendToStream(
            $this->linkedStreamName,
            ExpectedVersion::Any,
            [new EventData(null, SystemEventTypes::LinkTo->value, false, '0@' . $this->deletedStreamName)],
            $creds
        );

        $this->connection->deleteStream(
            $this->deletedStreamName,
            ExpectedVersion::Any
        );
    }
}
