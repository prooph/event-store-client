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

namespace ProophTest\EventStoreClient\Helper;

use Prooph\EventStore\EventData;
use Prooph\EventStore\EventStoreConnection;
use Prooph\EventStore\ExpectedVersion;

/** @internal */
class StreamWriter
{
    private EventStoreConnection $connection;

    private string $stream;

    private int $version;

    public function __construct(EventStoreConnection $connection, string $stream, int $version)
    {
        $this->connection = $connection;
        $this->stream = $stream;
        $this->version = $version;
    }

    /**
     * @param EventData[] $events
     */
    public function append(array $events): TailWriter
    {
        foreach ($events as $key => $event) {
            $expVer = $this->version === ExpectedVersion::Any ? ExpectedVersion::Any : $this->version + $key;
            $result = $this->connection->appendToStream($this->stream, $expVer, [$event]);
            $nextExpVer = $result->nextExpectedVersion();

            if ($this->version !== ExpectedVersion::Any
                && ($expVer + 1) !== $nextExpVer
            ) {
                throw new \RuntimeException('Wrong next expected version');
            }
        }

        return new TailWriter($this->connection, $this->stream);
    }
}
