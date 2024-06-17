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

namespace Prooph\EventStoreClient;

use Prooph\EventStore\EndPoint;

/**
 * Represents a source of cluster gossip
 *
 * @psalm-immutable
 */
class GossipSeed
{
    public function __construct(
        private readonly EndPoint $endPoint,
        private readonly string $hostHeader = '',
        private readonly bool $seedOverTls = true
    ) {
    }

    public function endPoint(): EndPoint
    {
        return $this->endPoint;
    }

    public function hostHeader(): string
    {
        return $this->hostHeader;
    }

    public function seedOverTls(): bool
    {
        return $this->seedOverTls;
    }
}
