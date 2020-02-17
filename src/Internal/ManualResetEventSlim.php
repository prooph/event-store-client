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

namespace Prooph\EventStoreClient\Internal;

use Amp\Deferred;
use Amp\Promise;

/** @internal */
class ManualResetEventSlim
{
    private bool $isSet;
    private Deferred $deferred;

    public function __construct(bool $isSet = false)
    {
        $this->isSet = $isSet;
        $this->deferred = new Deferred();

        if ($isSet) {
            $this->deferred->resolve(true);
        }
    }

    public function set(): void
    {
        $this->isSet = true;
        $this->deferred->resolve(true);
    }

    public function reset(): void
    {
        $this->isSet = false;
        $this->deferred = new Deferred();
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
