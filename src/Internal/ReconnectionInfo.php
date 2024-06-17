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

/**
 * @internal
 *
 * @psalm-immutable
 */
class ReconnectionInfo
{
    public function __construct(
        private readonly int $reconnectionAttempt,
        private readonly float $timestamp
    ) {
    }

    public function reconnectionAttempt(): int
    {
        return $this->reconnectionAttempt;
    }

    public function timestamp(): float
    {
        return $this->timestamp;
    }
}
