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
use Prooph\EventStore\Async\EventStoreTransaction;
use Prooph\EventStore\EventData;

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
     * @return Promise<OngoingTransaction>
     */
    public function writeAsync(array $events): Promise
    {
        return call(function () use ($events): Generator {
            yield $this->transaction->writeAsync($events);

            return $this;
        });
    }

    /** @return Promise<WriteResult> */
    public function commitAsync(): Promise
    {
        return call(fn (): Generator => yield $this->transaction->commitAsync());
    }
}
