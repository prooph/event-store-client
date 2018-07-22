<?php
/**
 * This file is part of the prooph/event-store-client.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient;

/**
 * All times are milliseconds
 */
class ClusterSettings
{
    /** @var string */
    private $clusterDns;
    /** @var int */
    private $maxDiscoverAttempts;
    /** @var int */
    private $externalGossipPort;
    /** @var GossipSeed[] */
    private $gossipSeeds;
    /** @var int */
    private $gossipTimeout;
    /** @var bool */
    private $preferRandomNode;

    public function __construct(
        string $clusterDns,
        int $maxDiscoverAttempts,
        int $externalGossipPort,
        array $gossipSeeds,
        int $gossipTimeout,
        bool $preferRandomNode
    ) {
        $this->clusterDns = $clusterDns;
        $this->maxDiscoverAttempts = $maxDiscoverAttempts;
        $this->externalGossipPort = $externalGossipPort;
        $this->gossipSeeds = $gossipSeeds;
        $this->gossipTimeout = $gossipTimeout;
        $this->preferRandomNode = $preferRandomNode;
    }

    public function clusterDns(): string
    {
        return $this->clusterDns;
    }

    public function maxDiscoverAttempts(): int
    {
        return $this->maxDiscoverAttempts;
    }

    public function externalGossipPort(): int
    {
        return $this->externalGossipPort;
    }

    public function gossipSeeds(): array
    {
        return $this->gossipSeeds;
    }

    public function gossipTimeout(): int
    {
        return $this->gossipTimeout;
    }

    public function preferRandomNode(): bool
    {
        return $this->preferRandomNode;
    }
}
