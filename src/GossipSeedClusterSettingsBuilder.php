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

namespace Prooph\EventStoreClient;

use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Exception\OutOfRangeException;
use Prooph\EventStore\Internal\Consts;

class GossipSeedClusterSettingsBuilder
{
    /** @var GossipSeed[] */
    private array $gossipSeeds = [];
    private int $maxDiscoverAttempts = Consts::DEFAULT_MAX_CLUSTER_DISCOVER_ATTEMPTS;
    private int $gossipTimeout = 1000;
    private bool $preferRandomNode = false;

    public function addEndPoint(EndPoint $endPoint): self
    {
        $this->gossipSeeds[] = new GossipSeed($endPoint);

        return $this;
    }

    /**
     * @param EndPoint[] $endPoints
     */
    public function addEndPoints(array $endPoints): self
    {
        foreach ($endPoints as $endPoint) {
            $this->addEndPoint($endPoint);
        }

        return $this;
    }

    public function addGossipSeed(GossipSeed $gossipSeed): self
    {
        $this->gossipSeeds[] = $gossipSeed;

        return $this;
    }

    /**
     * @param GossipSeed[] $gossipSeeds
     */
    public function addGossipSeeds(array $gossipSeeds): self
    {
        foreach ($gossipSeeds as $gossipSeed) {
            $this->addGossipSeed($gossipSeed);
        }

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

    public function setGossipTimeout(int $timeout): self
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
