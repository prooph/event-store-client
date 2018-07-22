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

use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Psr\Log\LoggerInterface as Log;

/**
 * All times are milliseconds
 */
class ConnectionSettings
{
    /** @var Log */
    private $log;
    /** @var bool */
    private $verboseLogging;
    /** @var int */
    private $maxQueueSize;
    /** @var int */
    private $maxConcurrentItems;
    /** @var int */
    private $maxRetries;
    /** @var int */
    private $maxReconnections;
    /** @var bool */
    private $requireMaster;
    /** @var int */
    private $reconnectionDelay;
    /** @var int */
    private $operationTimeout;
    /** @var int */
    private $operationTimeoutCheckPeriod;
    /** @var UserCredentials|null */
    private $defaultUserCredentials;
    /** @var bool */
    private $useSslConnection;
    /** @var string */
    private $targetHost = '';
    /** @var bool */
    private $validateServer;
    /** @var bool */
    private $failOnNoServerResponse;
    /** @var int */
    private $heartbeatInterval;
    /** @var int */
    private $heartbeatTimeout;
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
    /** @var int */
    private $clientConnectionTimeout;

    public static function default(): self
    {
        return (new ConnectionSettingsBuilder())->build();
    }

    public function __construct(
        Log $logger,
        bool $verboseLoggin,
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
            throw new InvalidArgumentException('Heartbeat interval must be less then 5000ms');
        }

        $this->log = $logger;
        $this->verboseLogging = $verboseLoggin;
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

    public function log(): Log
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

    public function reconnectionDelay(): int
    {
        return $this->reconnectionDelay;
    }

    public function operationTimeout(): int
    {
        return $this->operationTimeout;
    }

    public function operationTimeoutCheckPeriod(): int
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

    public function heartbeatInterval(): int
    {
        return $this->heartbeatInterval;
    }

    public function heartbeatTimeout(): int
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

    public function gossipTimeout(): int
    {
        return $this->gossipTimeout;
    }

    public function preferRandomNode(): bool
    {
        return $this->preferRandomNode;
    }

    public function clientConnectionTimeout(): int
    {
        return $this->clientConnectionTimeout;
    }
}
