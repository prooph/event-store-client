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

namespace Prooph\EventStoreClient\Internal;

/**
 * @internal
 *
 * @psalm-immutable
 */
class ReconnectionInfo
{
    private int $reconnectionAttempt;
    private int $timestamp;

    public function __construct(int $reconnectionAttempt, int $timestamp)
    {
        $this->reconnectionAttempt = $reconnectionAttempt;
        $this->timestamp = $timestamp;
    }

    /** @psalm-pure */
    public function reconnectionAttempt(): int
    {
        return $this->reconnectionAttempt;
    }

    /** @psalm-pure */
    public function timestamp(): int
    {
        return $this->timestamp;
    }
}
