<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2021 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2021 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Success;
use Generator;
use Prooph\EventStore\Async\EventStoreConnection;
use ProophTest\EventStoreClient\Helper\TestConnection;

abstract class EventStoreConnectionTestCase extends AsyncTestCase
{
    protected EventStoreConnection $connection;

    protected function setUpAsync(): Generator
    {
        $this->connection = TestConnection::create();
        yield $this->connection->connectAsync();
    }

    protected function tearDownAsync(): Generator
    {
        $this->connection->close();

        yield new Success();
    }
}
