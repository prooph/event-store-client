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
    private Logger $log;
    private bool $verboseLogging;
    private int $maxQueueSize;
    private int $maxConcurrentItems;
    private int $maxRetries;
    private int $maxReconnections;
    private bool $requireMaster;
    private int $reconnectionDelay;
    private int $operationTimeout;
    private int $operationTimeoutCheckPeriod;
    private ?UserCredentials $defaultUserCredentials;
    private bool $useSslConnection;
    private string $targetHost = '';
    private bool $validateServer;
    private bool $failOnNoServerResponse;
    private int $heartbeatInterval;
    private int $heartbeatTimeout;
    private string $clusterDns;
    private int $maxDiscoverAttempts;
    private int $externalGossipPort;
    /** @var list<GossipSeed> */
    private array $gossipSeeds = [];
    private int $gossipTimeout;
    private bool $preferRandomNode;
    private int $clientConnectionTimeout;

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
        Logger $logger,
        bool $verboseLogging,
        int $maxQueueSize,
        int $maxConcurrentItems,
        int $maxRetries,
        int $maxReconnections,
        bool $requireMaster,
        int $reconnectionDelay,
        int $operationTimeout,
        int $operationTimeoutCheckPeriod,
        ?UserCredentials $defaultUserCredentials,
        bool $useSslConnection,
        string $targetHost,
        bool $validateServer,
        bool $failOnNoServerResponse,
        int $heartbeatInterval,
        int $heartbeatTimeout,
        string $clusterDns,
        int $maxDiscoverAttempts,
        int $externalGossipPort,
        array $gossipSeeds,
        int $gossipTimeout,
        bool $preferRandomNode,
        int $clientConnectionTimeout
    ) {
        if ($heartbeatInterval >= 5000) {
            throw new InvalidArgumentException('Heartbeat interval must be less than 5000ms');
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

        $this->log = $logger;
        $this->verboseLogging = $verboseLogging;
        $this->maxQueueSize = $maxQueueSize;
        $this->maxConcurrentItems = $maxConcurrentItems;
        $this->maxRetries = $maxRetries;
        $this->maxReconnections = $maxReconnections;
        $this->requireMaster = $requireMaster;
        $this->reconnectionDelay = $reconnectionDelay;
        $this->operationTimeout = $operationTimeout;
        $this->operationTimeoutCheckPeriod = $operationTimeoutCheckPeriod;
        $this->defaultUserCredentials = $defaultUserCredentials;
        $this->useSslConnection = $useSslConnection;
        $this->targetHost = $targetHost;
        $this->validateServer = $validateServer;
        $this->failOnNoServerResponse = $failOnNoServerResponse;
        $this->heartbeatInterval = $heartbeatInterval;
        $this->heartbeatTimeout = $heartbeatTimeout;
        $this->clusterDns = $clusterDns;
        $this->maxDiscoverAttempts = $maxDiscoverAttempts;
        $this->externalGossipPort = $externalGossipPort;
        $this->gossipSeeds = $gossipSeeds;
        $this->gossipTimeout = $gossipTimeout;
        $this->preferRandomNode = $preferRandomNode;
        $this->clientConnectionTimeout = $clientConnectionTimeout;
    }

    /** @psalm-pure */
    public function log(): Logger
    {
        return $this->log;
    }

    /** @psalm-pure */
    public function verboseLogging(): bool
    {
        return $this->verboseLogging;
    }

    /** @psalm-pure */
    public function maxQueueSize(): int
    {
        return $this->maxQueueSize;
    }

    /** @psalm-pure */
    public function maxConcurrentItems(): int
    {
        return $this->maxConcurrentItems;
    }

    /** @psalm-pure */
    public function maxRetries(): int
    {
        return $this->maxRetries;
    }

    /** @psalm-pure */
    public function maxReconnections(): int
    {
        return $this->maxReconnections;
    }

    /** @psalm-pure */
    public function requireMaster(): bool
    {
        return $this->requireMaster;
    }

    /** @psalm-pure */
    public function reconnectionDelay(): int
    {
        return $this->reconnectionDelay;
    }

    /** @psalm-pure */
    public function operationTimeout(): int
    {
        return $this->operationTimeout;
    }

    /** @psalm-pure */
    public function operationTimeoutCheckPeriod(): int
    {
        return $this->operationTimeoutCheckPeriod;
    }

    /** @psalm-pure */
    public function defaultUserCredentials(): ?UserCredentials
    {
        return $this->defaultUserCredentials;
    }

    /** @psalm-pure */
    public function useSslConnection(): bool
    {
        return $this->useSslConnection;
    }

    /** @psalm-pure */
    public function targetHost(): string
    {
        return $this->targetHost;
    }

    /** @psalm-pure */
    public function validateServer(): bool
    {
        return $this->validateServer;
    }

    /** @psalm-pure */
    public function failOnNoServerResponse(): bool
    {
        return $this->failOnNoServerResponse;
    }

    /** @psalm-pure */
    public function heartbeatInterval(): int
    {
        return $this->heartbeatInterval;
    }

    /** @psalm-pure */
    public function heartbeatTimeout(): int
    {
        return $this->heartbeatTimeout;
    }

    /** @psalm-pure */
    public function clusterDns(): string
    {
        return $this->clusterDns;
    }

    /** @psalm-pure */
    public function maxDiscoverAttempts(): int
    {
        return $this->maxDiscoverAttempts;
    }

    /** @psalm-pure */
    public function externalGossipPort(): int
    {
        return $this->externalGossipPort;
    }

    /** @psalm-pure */
    public function gossipSeeds(): array
    {
        return $this->gossipSeeds;
    }

    /** @psalm-pure */
    public function gossipTimeout(): int
    {
        return $this->gossipTimeout;
    }

    /** @psalm-pure */
    public function preferRandomNode(): bool
    {
        return $this->preferRandomNode;
    }

    /** @psalm-pure */
    public function clientConnectionTimeout(): int
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
