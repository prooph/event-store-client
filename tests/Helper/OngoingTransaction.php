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
use Prooph\EventStore\EventStoreTransaction;
use Prooph\EventStore\WriteResult;

/** @internal */
class OngoingTransaction
{
    private EventStoreTransaction $transaction;

    public function __construct(EventStoreTransaction $transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     * @param EventData[] $events
     */
    public function write(array $events): OngoingTransaction
    {
        $this->transaction->write($events);

        return $this;
    }

    public function commit(): WriteResult
    {
        return $this->transaction->commit();
    }
}
