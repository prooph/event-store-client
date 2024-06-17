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

use Closure;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventStoreConnection;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\Util\Guid;
use Prooph\EventStoreClient\Projections\ProjectionsManager;
use ProophTest\EventStoreClient\Helper\TestConnection;

trait ProjectionSpecification
{
    protected ProjectionsManager $projectionsManager;

    protected EventStoreConnection $connection;

    protected UserCredentials $credentials;

    protected function given(): void
    {
    }

    abstract protected function when(): void;

    protected function execute(Closure $test): void
    {
        $this->credentials = DefaultData::adminCredentials();
        $this->connection = TestConnection::create();

        $this->connection->connect();

        $this->projectionsManager = new ProjectionsManager(
            TestConnection::httpEndPoint(),
            5
        );

        $this->given();
        $this->when();

        try {
            $test();
        } finally {
            $this->end();
        }
    }

    protected function end(): void
    {
        $this->connection->close();
    }

    protected function createEvent(string $eventType, string $data, string $metadata = ''): EventData
    {
        return new EventData(null, $eventType, true, $data, $metadata);
    }

    protected function postEvent(string $stream, string $eventType, string $data, string $metadata = ''): void
    {
        $this->connection->appendToStream(
            $stream,
            ExpectedVersion::Any,
            [$this->createEvent($eventType, $data, $metadata)]
        );
    }

    protected function createOneTimeProjection(): void
    {
        $query = $this->createStandardQuery(Guid::generateAsHex());

        $this->projectionsManager->createOneTime($query, 'JS', $this->credentials);
    }

    protected function createContinuousProjection(string $projectionName): void
    {
        $query = $this->createStandardQuery(Guid::generateAsHex());

        $this->projectionsManager->createContinuous(
            $projectionName,
            $query,
            false,
            'JS',
            $this->credentials
        );
    }

    protected function createStandardQuery(string $stream): string
    {
        return <<<QUERY
fromStream('$stream').when({
    '\$any': function (s, e) {
        s.count = 1;
        return s;
    }
});
QUERY;
    }

    protected function createEmittingQuery(string $stream, string $emittingStream): string
    {
        return <<<QUERY
fromStream('$stream').when({
    '\$any': function (s, e) {
        emit('$emittingStream', 'emittedEvent', e);
    }
});
QUERY;
    }

    protected function createPartitionedQuery($stream): string
    {
        return <<<QUERY
fromStream('$stream').partitionBy(function(e){
    return e.metadata.username;
}).when({
    '\$any': function (s, e) {
        s.count = 1;
        return s;
    }
});
QUERY;
    }
}
