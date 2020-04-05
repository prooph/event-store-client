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


namespace ProophTest\EventStoreClient;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Prooph\EventStore\Async\EventStoreConnection;
use ProophTest\EventStoreClient\Helper\TestConnection;

abstract class EventStoreConnectionTestCase extends AsyncTestCase
{
    protected EventStoreConnection $connection;

    protected function setUpAsync(): Promise
    {
        $this->connection = TestConnection::create();
        $this->connection->connectAsync();

        return parent::setUpAsync();
    }

    protected function tearDownAsync(): Promise
    {
        $this->connection->close();

        return parent::tearDownAsync();
    }
}
