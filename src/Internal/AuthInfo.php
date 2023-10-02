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
class AuthInfo
{
    public function __construct(
        private readonly string $correlationId,
        private readonly float $timestamp
    ) {
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function timestamp(): float
    {
        return $this->timestamp;
    }
}
