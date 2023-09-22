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

namespace ProophTest\EventStoreClient;

use Amp\CancelledException;
use Amp\DeferredFuture;
use Amp\TimeoutCancellation;
use InvalidArgumentException;
use Prooph\EventStore\Exception\RuntimeException;

/** @internal */
class CountdownEvent
{
    private int $counter;

    private DeferredFuture $deferred;

    public function __construct(int $counter)
    {
        if ($counter < 1) {
            throw new InvalidArgumentException('Counter must be positive');
        }

        $this->counter = $counter;
        $this->deferred = new DeferredFuture();
    }

    public function signal(): void
    {
        if (0 === $this->counter) {
            throw new RuntimeException('CountdownEvent already resolved');
        }

        --$this->counter;

        if (0 === $this->counter) {
            $this->deferred->complete(true);
        }
    }

    public function wait(int $timeout): bool
    {
        try {
            return $this->deferred->getFuture()->await(new TimeoutCancellation($timeout));
        } catch (CancelledException $e) {
            return false;
        }
    }
}
