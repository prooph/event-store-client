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

namespace Prooph\EventStoreClient\Internal;

/** @internal */
class StopWatch
{
    private function __construct(private readonly float $started)
    {
    }

    public static function startNew(): self
    {
        $started = \microtime(true);

        return new self($started);
    }

    public function elapsed(): float
    {
        $timestamp = \microtime(true);

        return $timestamp - $this->started;
    }
}
