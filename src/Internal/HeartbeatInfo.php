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
class HeartbeatInfo
{
    public function __construct(
        private readonly int $lastPackageNumber,
        private readonly bool $isIntervalStage,
        private readonly int $timestamp
    ) {
    }

    public function lastPackageNumber(): int
    {
        return $this->lastPackageNumber;
    }

    public function isIntervalStage(): bool
    {
        return $this->isIntervalStage;
    }

    public function timestamp(): int
    {
        return $this->timestamp;
    }
}
