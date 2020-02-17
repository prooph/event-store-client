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

use Amp\Deferred;
use Amp\Promise;
use InvalidArgumentException;
use Prooph\EventStore\Exception\RuntimeException;

/** @internal */
class CountdownEvent
{
    private int $counter;
    private Deferred $deferred;

    public function __construct(int $counter)
    {
        if ($counter < 1) {
            throw new InvalidArgumentException('Counter must be positive');
        }

        $this->counter = $counter;
        $this->deferred = new Deferred();
    }

    public function signal(): void
    {
        if (0 === $this->counter) {
            throw new RuntimeException('CountdownEvent already resolved');
        }

        --$this->counter;

        if (0 === $this->counter) {
            $this->deferred->resolve(true);
        }
    }

    public function wait(int $timeout): Promise
    {
        $promise = Promise\timeout($this->deferred->promise(), $timeout);

        $deferred = new Deferred();
        $newPromise = $deferred->promise();

        $promise->onResolve(function (?\Throwable $exception = null, $result) use ($deferred): void {
            if ($exception) {
                $deferred->resolve(false);
            } else {
                $deferred->resolve(true);
            }
        });

        return $newPromise;
    }
}
