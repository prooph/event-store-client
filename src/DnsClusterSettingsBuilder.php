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

use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Exception\OutOfRangeException;
use Prooph\EventStore\Internal\Consts;

class DnsClusterSettingsBuilder
{
    private string $clusterDns;
    private int $maxDiscoverAttempts = Consts::DEFAULT_MAX_CLUSTER_DISCOVER_ATTEMPTS;
    private int $managerExternalHttpPort = Consts::DEFAULT_CLUSTER_MANAGER_EXTERNAL_HTTP_PORT;
    private int $gossipTimeout = 1000;
    private bool $preferRandomNode = false;

    public function setClusterDns(string $clusterDns): self
    {
        if (empty($clusterDns)) {
            throw new InvalidArgumentException('Cluster DNS cannot be empty');
        }

        $this->clusterDns = $clusterDns;

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

    public function setClusterGossipPort(int $clusterGossipPort): self
    {
        if ($clusterGossipPort < 1) {
            throw new OutOfRangeException('Cluster Gossip Port must be positive');
        }

        $this->managerExternalHttpPort = $clusterGossipPort;

        return $this;
    }

    public function build(): ClusterSettings
    {
        return ClusterSettings::fromClusterDns(
            $this->clusterDns,
            $this->maxDiscoverAttempts,
            $this->managerExternalHttpPort,
            $this->gossipTimeout,
            $this->preferRandomNode
        );
    }
}
