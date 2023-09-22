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

use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Exception\OutOfRangeException;
use Prooph\EventStore\UserCredentials;
use Psr\Log\LoggerInterface as Logger;

/**
 * All times are milliseconds
 */
final class ConnectionSettings
{
    public static function default(): self
    {
        return (new ConnectionSettingsBuilder())->build();
    }

    public static function create(): ConnectionSettingsBuilder
    {
        return new ConnectionSettingsBuilder();
    }

    /**
     * @internal
     *
     * @param list<GossipSeed> $gossipSeeds
     */
    public function __construct(
        private Logger $log,
        private bool $verboseLogging,
        private int $maxQueueSize,
        private int $maxConcurrentItems,
        private int $maxRetries,
        private int $maxReconnections,
        private bool $requireMaster,
        private float $reconnectionDelay,
        private float $operationTimeout,
        private float $operationTimeoutCheckPeriod,
        private ?UserCredentials $defaultUserCredentials,
        private bool $useSslConnection,
        private string $targetHost,
        private bool $validateServer,
        private bool $failOnNoServerResponse,
        private float $heartbeatInterval,
        private float $heartbeatTimeout,
        private string $clusterDns,
        private int $maxDiscoverAttempts,
        private int $externalGossipPort,
        private array $gossipSeeds,
        private float $gossipTimeout,
        private bool $preferRandomNode,
        private float $clientConnectionTimeout
    ) {
        if ($heartbeatInterval >= 5) {
            throw new InvalidArgumentException('Heartbeat interval must be less than 5 sec');
        }

        if ($maxQueueSize < 1) {
            throw new InvalidArgumentException('Max queue size must be positive');
        }

        if ($maxConcurrentItems < 1) {
            throw new InvalidArgumentException('Max concurrent items must be positive');
        }

        if ($maxRetries < -1) {
            throw new OutOfRangeException(\sprintf(
                'Max retries is out of range %d. Allowed range: [-1, PHP_INT_MAX].',
                $maxReconnections
            ));
        }

        if ($maxReconnections < -1) {
            throw new OutOfRangeException(\sprintf(
                'Max reconnections is out of range %d. Allowed range: [-1, PHP_INT_MAX].',
                $maxReconnections
            ));
        }

        if ($useSslConnection && empty($targetHost)) {
            throw new InvalidArgumentException('Target host must be not empty when using SSL');
        }
    }

    public function log(): Logger
    {
        return $this->log;
    }

    public function verboseLogging(): bool
    {
        return $this->verboseLogging;
    }

    public function maxQueueSize(): int
    {
        return $this->maxQueueSize;
    }

    public function maxConcurrentItems(): int
    {
        return $this->maxConcurrentItems;
    }

    public function maxRetries(): int
    {
        return $this->maxRetries;
    }

    public function maxReconnections(): int
    {
        return $this->maxReconnections;
    }

    public function requireMaster(): bool
    {
        return $this->requireMaster;
    }

    public function reconnectionDelay(): float
    {
        return $this->reconnectionDelay;
    }

    public function operationTimeout(): float
    {
        return $this->operationTimeout;
    }

    public function operationTimeoutCheckPeriod(): float
    {
        return $this->operationTimeoutCheckPeriod;
    }

    public function defaultUserCredentials(): ?UserCredentials
    {
        return $this->defaultUserCredentials;
    }

    public function useSslConnection(): bool
    {
        return $this->useSslConnection;
    }

    public function targetHost(): string
    {
        return $this->targetHost;
    }

    public function validateServer(): bool
    {
        return $this->validateServer;
    }

    public function failOnNoServerResponse(): bool
    {
        return $this->failOnNoServerResponse;
    }

    public function heartbeatInterval(): float
    {
        return $this->heartbeatInterval;
    }

    public function heartbeatTimeout(): float
    {
        return $this->heartbeatTimeout;
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

    public function gossipTimeout(): float
    {
        return $this->gossipTimeout;
    }

    public function preferRandomNode(): bool
    {
        return $this->preferRandomNode;
    }

    public function clientConnectionTimeout(): float
    {
        return $this->clientConnectionTimeout;
    }

    public function withDefaultCredentials(UserCredentials $userCredentials): self
    {
        $clone = clone $this;
        $clone->defaultUserCredentials = $userCredentials;

        return $clone;
    }
}
