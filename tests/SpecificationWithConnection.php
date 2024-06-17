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

use Closure;
use Prooph\EventStore\EventStoreConnection;
use ProophTest\EventStoreClient\Helper\TestConnection;

trait SpecificationWithConnection
{
    protected EventStoreConnection $connection;

    protected function given(): void
    {
    }

    protected function when(): void
    {
    }

    protected function execute(Closure $test): void
    {
        $this->connection = TestConnection::create();
        $this->connection->connect();

        try {
            $this->given();
            $this->when();
            $test();
        } finally {
            $this->end();
        }
    }

    protected function end(): void
    {
        $this->connection->close();
    }
}
