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

use Amp\Success;
use Generator;
use Prooph\EventStoreClient\EventStoreAsyncConnection;
use ProophTest\EventStoreClient\Helper\Connection;
use function Amp\call;
use function Amp\Promise\wait;

trait SpecificationWithConnection
{
    /** @var EventStoreAsyncConnection */
    protected $conn;

    protected function given(): Generator
    {
        yield new Success();
    }

    abstract protected function when(): Generator;

    /** @throws \Throwable */
    protected function executeCallback(callable $test): void
    {
        wait(call(function () use ($test) {
            $this->conn = Connection::createAsync();

            yield $this->conn->connectAsync();

            yield from $this->given();

            yield from $this->when();

            yield from $test();

            yield from $this->end();
        }));
    }

    protected function end(): Generator
    {
        $this->conn->close();

        yield new Success();
    }
}
