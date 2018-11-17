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

use Prooph\EventStoreClient\Exception\OutOfRangeException;
use Prooph\EventStoreClient\Internal\Consts;

class GossipSeedClusterSettingsBuilder
{
    /** @var GossipSeed[] */
    private $gossipSeeds = [];
    /** @var int */
    private $maxDiscoverAttempts = Consts::DEFAULT_MAX_CLUSTER_DISCOVER_ATTEMPTS;
    /** @var int */
    private $gossipTimeout = 1000;
    /** @var bool */
    private $preferRandomNode = false;

    public function addEndPoint(EndPoint $endPoint): self
    {
        $this->gossipSeeds[] = new GossipSeed($endPoint);

        return $this;
    }

    /**
     * @param EndPoint[] $endPoints
     *
     * @return GossipSeedClusterSettingsBuilder
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
     *
     * @return GossipSeedClusterSettingsBuilder
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
