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

use Amp\Cache\Cache;
use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use Prooph\EventStore\ExpectedVersion;

class ParallelTransactionTask implements Task
{
    private readonly string $stream;

    private readonly string $metadata;

    public function __construct(string $stream, string $metadata)
    {
        $this->stream = $stream;
        $this->metadata = $metadata;
    }

    public function run(Channel $channel, Cache $cache, Cancellation $cancellation): mixed
    {
        $store = TestConnection::create();

        $store->connect();

        $transaction = $store->startTransaction(
            $this->stream,
            ExpectedVersion::Any
        );

        for ($i = 0; $i < 250; $i++) {
            $transaction->write(
                [TestEvent::newTestEvent(null, (string) $i, $this->metadata)]
            );
        }

        $transaction->commit();

        $store->close();

        return true;
    }
}
