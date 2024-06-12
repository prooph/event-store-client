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

namespace Prooph\EventStoreClient;

use Prooph\EventStore\EndPoint;

/**
 * Represents a source of cluster gossip
 *
 * @psalm-immutable
 */
readonly class GossipSeed
{
    public function __construct(
        public EndPoint $endPoint,
        public string $hostHeader = '',
        public bool $seedOverTls = true)
    {
    }
}
