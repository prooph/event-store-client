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

/** @internal */
class ReconnectionInfo
{
    private int $reconnectionAttempt;
    private int $timestamp;

    public function __construct(int $reconnectionAttempt, int $timestamp)
    {
        $this->reconnectionAttempt = $reconnectionAttempt;
        $this->timestamp = $timestamp;
    }

    public function reconnectionAttempt(): int
    {
        return $this->reconnectionAttempt;
    }

    public function timestamp(): int
    {
        return $this->timestamp;
    }
}
