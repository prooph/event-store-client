<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient\Helper;

use Amp\Promise;
use Amp\Success;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\EventStoreAsyncConnection;
use function Amp\call;

/** @internal */
class TailWriter
{
    /** @var EventStoreAsyncConnection */
    private $connection;
    /** @var string */
    private $stream;

    public function __construct(EventStoreAsyncConnection $connection, string $stream)
    {
        $this->connection = $connection;
        $this->stream = $stream;
    }

    /** @return Promise<TailWriter> */
    public function then(EventData $event, int $expectedVersion): Promise
    {
        return call(function () use ($event, $expectedVersion) {
            yield $this->connection->appendToStreamAsync($this->stream, $expectedVersion, [$event]);

            return new Success($this);
        });
    }
}
