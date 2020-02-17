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

use Prooph\EventStore\Util\DateTime;

/** @internal */
class StopWatch
{
    private int $started;

    private function __construct(int $started)
    {
        $this->started = $started;
    }

    public static function startNew(): self
    {
        $now = DateTime::utcNow();
        $started = (int) \floor((float) $now->format('U.u') * 1000);

        return new self($started);
    }

    public function elapsed(): int
    {
        $now = DateTime::utcNow();
        $timestamp = (int) \floor((float) $now->format('U.u') * 1000);

        return $timestamp - $this->started;
    }
}
