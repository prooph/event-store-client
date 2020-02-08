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
use Generator;
use Prooph\EventStore\Async\EventStoreConnection;

/** @internal */
class TransactionalWriter
{
    private EventStoreConnection $connection;
    private string $stream;

    public function __construct(EventStoreConnection $connection, string $stream)
    {
        $this->connection = $connection;
        $this->stream = $stream;
    }

    /**
     * @param int $expectedVersion
     * @return Promise<OngoingTransaction>
     */
    public function startTransaction(int $expectedVersion): Promise
    {
        return call(function () use ($expectedVersion): Generator {
            $transaction = yield $this->connection->startTransactionAsync(
                $this->stream,
                $expectedVersion
            );

            return new OngoingTransaction($transaction);
        });
    }
}
