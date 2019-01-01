<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2019 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient\Helper;

use Amp\Promise;
use Generator;
use Prooph\EventStore\AsyncEventStoreTransaction;
use Prooph\EventStore\EventData;
use function Amp\call;

/** @internal */
class OngoingTransaction
{
    /** @var AsyncEventStoreTransaction */
    private $transaction;

    public function __construct(AsyncEventStoreTransaction $transaction)
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
        return call(function (): Generator {
            return yield $this->transaction->commitAsync();
        });
    }
}
