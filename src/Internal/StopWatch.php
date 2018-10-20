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

namespace Prooph\EventStoreClient\Internal;

/** @internal */
class StopWatch
{
    /** @var int */
    private $started;

    private function __construct(int $started)
    {
        $this->started = $started;
    }

    public static function startNew(): self
    {
        $now = DateTimeUtil::utcNow();
        $started = (int) \floor((float) $now->format('U.u') * 1000);

        return new self($started);
    }

    public function elapsed(): int
    {
        $now = DateTimeUtil::utcNow();
        $timestamp = (int) \floor((float) $now->format('U.u') * 1000);

        return $timestamp - $this->started;
    }
}
