<?php
/**
 * This file is part of the prooph/event-store-client.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\Loop;
use Generator;
use Prooph\EventStoreClient\EventStoreAsyncConnection;
use ProophTest\EventStoreClient\Helper\Connection;

trait SpecificationWithConnection
{
    /** @var EventStoreAsyncConnection */
    protected $conn;

    protected function given(): void
    {
    }

    abstract protected function when(): Generator;

    protected function executeCallback(callable $test): void
    {
        Loop::run(function () use ($test) {
            $this->conn = Connection::createAsync();

            yield $this->conn->connectAsync();

            $this->given();

            yield from $this->when();

            yield from $test();

            $this->conn->close();
        });
    }
}
