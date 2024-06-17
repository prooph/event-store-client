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

use Prooph\EventStore\Exception\OutOfRangeException;
use Prooph\EventStore\Internal\Consts;

class GossipSeedClusterSettingsBuilder
{
    /** @var list<GossipSeed> */
    private array $gossipSeeds = [];

    private int $maxDiscoverAttempts = Consts::DefaultMaxClusterDiscoverAttempts;

    private float $gossipTimeout = 1;

    private bool $preferRandomNode = false;

    /**
     * @param list<GossipSeed> $gossipSeeds
     */
    public function setGossipSeedEndPoints(array $gossipSeeds): self
    {
        $this->gossipSeeds = $gossipSeeds;

        return $this;
    }

    public function setMaxDiscoverAttempts(int $maxDiscoverAttempts): self
    {
        if ($maxDiscoverAttempts < 1) {
            throw new OutOfRangeException(\sprintf(
                'Max discover attempts value is out of range: %d. Allowed range: [1, PHP_INT_MAX]',
                $maxDiscoverAttempts
            ));
        }

        $this->maxDiscoverAttempts = $maxDiscoverAttempts;

        return $this;
    }

    public function setGossipTimeout(float $timeout): self
    {
        $this->gossipTimeout = $timeout;

        return $this;
    }

    public function preferRandomNode(): self
    {
        $this->preferRandomNode = true;

        return $this;
    }

    public function build(): ClusterSettings
    {
        return ClusterSettings::fromGossipSeeds(
            $this->gossipSeeds,
            $this->maxDiscoverAttempts,
            $this->gossipTimeout,
            $this->preferRandomNode
        );
    }
}
