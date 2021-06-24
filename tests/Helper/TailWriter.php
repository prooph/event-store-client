<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2021 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2021 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient\Helper;

use function Amp\call;
use Amp\Promise;
use Generator;
use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\EventData;

/** @internal */
class TailWriter
{
    private EventStoreConnection $connection;
    private string $stream;

    public function __construct(EventStoreConnection $connection, string $stream)
    {
        $this->connection = $connection;
        $this->stream = $stream;
    }

    /** @return Promise<TailWriter> */
    public function then(EventData $event, int $expectedVersion): Promise
    {
        return call(function () use ($event, $expectedVersion): Generator {
            yield $this->connection->appendToStreamAsync($this->stream, $expectedVersion, [$event]);

            return $this;
        });
    }
}
