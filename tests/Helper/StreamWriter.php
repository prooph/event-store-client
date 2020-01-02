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

namespace ProophTest\EventStoreClient\Helper;

use function Amp\call;
use Amp\Promise;
use Amp\Success;
use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\WriteResult;

/** @internal */
class StreamWriter
{
    /** @var EventStoreConnection */
    private $connection;
    /** @var string */
    private $stream;
    /** @var int */
    private $version;

    public function __construct(EventStoreConnection $connection, string $stream, int $version)
    {
        $this->connection = $connection;
        $this->stream = $stream;
        $this->version = $version;
    }

    /** @return Promise<TailWriter> */
    public function append(array $events): Promise
    {
        return call(function () use ($events) {
            foreach ($events as $key => $event) {
                $expVer = $this->version === ExpectedVersion::ANY ? ExpectedVersion::ANY : $this->version + $key;
                $result = yield $this->connection->appendToStreamAsync($this->stream, $expVer, [$event]);
                \assert($result instanceof WriteResult);
                $nextExpVer = $result->nextExpectedVersion();

                if ($this->version !== ExpectedVersion::ANY
                    && ($expVer + 1) !== $nextExpVer
                ) {
                    throw new \RuntimeException('Wrong next expected version');
                }
            }

            return new Success(new TailWriter($this->connection, $this->stream));
        });
    }
}
