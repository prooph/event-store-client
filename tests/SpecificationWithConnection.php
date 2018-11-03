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

namespace ProophTest\EventStoreClient;

use Amp\Success;
use Generator;
use Prooph\EventStoreClient\EventStoreAsyncConnection;
use ProophTest\EventStoreClient\Helper\TestConnection;
use Throwable;
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

    protected function when(): Generator
    {
        yield new Success();
    }

    /** @throws Throwable */
    protected function execute(callable $test): void
    {
        wait(call(function () use ($test) {
            $this->conn = TestConnection::createAsync();

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
