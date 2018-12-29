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

namespace Prooph\EventStoreClient;

use Prooph\EventStore\EndPoint;

/**
 * Represents a source of cluster gossip
 */
class GossipSeed
{
    /** @var EndPoint */
    private $endPoint;
    /** @var string */
    private $hostHeader;

    public function __construct(EndPoint $endPoint, string $hostHeader = '')
    {
        $this->endPoint = $endPoint;
        $this->hostHeader = $hostHeader;
    }

    public function endPoint(): EndPoint
    {
        return $this->endPoint;
    }

    public function hostHeader(): string
    {
        return $this->hostHeader;
    }
}
