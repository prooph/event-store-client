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

use function Amp\call;
use Amp\Promise;
use Amp\Success;
use Closure;
use Generator;
use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\EventData;
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

    protected function given(): Generator
    {
        yield new Success();
    }

    abstract protected function when(): Generator;

    protected function execute(Closure $test): Promise
    {
        return call(function () use ($test): Generator {
            $this->credentials = DefaultData::adminCredentials();
            $this->connection = TestConnection::create();

            yield $this->connection->connectAsync();

            $this->projectionsManager = new ProjectionsManager(
                TestConnection::httpEndPoint(),
                5000
            );

            yield from $this->given();
            yield from $this->when();

            try {
                $result = yield from $test();
            } finally {
                yield from $this->end();
            }

            return $result;
        });
    }

    protected function end(): Generator
    {
        //$this->connection->close();

        yield new Success();
    }

    protected function createEvent(string $eventType, string $data, string $metadata = ''): EventData
    {
        return new EventData(null, $eventType, true, $data, $metadata);
    }

    protected function postEvent(string $stream, string $eventType, string $data, string $metadata = ''): Promise
    {
        return $this->connection->appendToStreamAsync(
            $stream,
            ExpectedVersion::ANY,
            [$this->createEvent($eventType, $data, $metadata)]
        );
    }

    protected function createOneTimeProjection(): Promise
    {
        $query = $this->createStandardQuery(Guid::generateAsHex());

        return $this->projectionsManager->createOneTimeAsync($query, 'JS', $this->credentials);
    }

    protected function createContinuousProjection(string $projectionName): Promise
    {
        $query = $this->createStandardQuery(Guid::generateAsHex());

        return $this->projectionsManager->createContinuousAsync(
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
