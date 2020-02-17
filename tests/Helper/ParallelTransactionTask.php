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

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use Amp\Promise;
use Prooph\EventStore\Async\EventStoreTransaction;
use Prooph\EventStore\ExpectedVersion;

class ParallelTransactionTask implements Task
{
    private string $stream;
    private string $metadata;

    public function __construct(string $stream, string $metadata)
    {
        $this->stream = $stream;
        $this->metadata = $metadata;
    }

    public function run(Environment $environment)
    {
        $store = TestConnection::create();

        yield $store->connectAsync();

        $transaction = yield $store->startTransactionAsync(
            $this->stream,
            ExpectedVersion::ANY
        );
        \assert($transaction instanceof EventStoreTransaction);

        $writes = [];

        for ($i = 0; $i < 250; $i++) {
            $writes[] = $transaction->writeAsync(
                [TestEvent::newTestEvent(null, (string) $i, $this->metadata)]
            );
        }

        yield Promise\all($writes);

        yield $transaction->commitAsync();

        return true;
    }
}
