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

use Prooph\EventStore\EventStoreConnection;

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

    public function startTransaction(int $expectedVersion): OngoingTransaction
    {
        $transaction = $this->connection->startTransaction(
            $this->stream,
            $expectedVersion
        );

        return new OngoingTransaction($transaction);
    }
}
