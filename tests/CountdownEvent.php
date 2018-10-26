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

use Amp\Deferred;
use Amp\Promise;
use InvalidArgumentException;
use Prooph\EventStoreClient\Exception\RuntimeException;

/** @internal */
class CountdownEvent
{
    /** @var int */
    private $counter;
    /** @var Deferred */
    private $deferred;

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

        $promise->onResolve(function (?\Throwable $exception = null, $result) use ($deferred) {
            if ($exception) {
                $deferred->resolve(false);
            } else {
                $deferred->resolve(true);
            }
        });

        return $newPromise;
    }
}
