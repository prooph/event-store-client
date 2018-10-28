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

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use Amp\Promise;
use Prooph\EventStoreClient\EventStoreAsyncTransaction;
use Prooph\EventStoreClient\ExpectedVersion;

class ParallelTransactionTask implements Task
{
    /** @var string */
    private $stream;
    /** @var string */
    private $metadata;

    public function __construct(string $stream, string $metadata)
    {
        $this->stream = $stream;
        $this->metadata = $metadata;
    }

    public function run(Environment $environment)
    {
        $store = TestConnection::createAsync();

        yield $store->connectAsync();

        /** @var EventStoreAsyncTransaction $transaction */
        $transaction = yield $store->startTransactionAsync(
            $this->stream,
            ExpectedVersion::ANY
        );

        $writes = [];

        for ($i = 0; $i < 500; $i++) {
            $writes[] = $transaction->writeAsync(
                [TestEvent::newTestEvent(null, (string) $i, $this->metadata)]
            );
        }

        yield Promise\all($writes);

        yield $transaction->commitAsync();

        return true;
    }
}
