<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2024 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2024 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\PHPUnit\AsyncTestCase;
use Prooph\EventStore\EventStoreConnection;
use ProophTest\EventStoreClient\Helper\TestConnection;

abstract class EventStoreConnectionTestCase extends AsyncTestCase
{
    protected EventStoreConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = TestConnection::create();
        $this->connection->connect();
    }

    protected function tearDown(): void
    {
        $this->connection->close();

        parent::tearDown();
    }
}
