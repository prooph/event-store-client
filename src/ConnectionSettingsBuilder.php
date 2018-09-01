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

use Amp\ByteStream\ResourceOutputStream;
use Amp\File\Handle;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Logger as MonoLog;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Internal\Consts;
use Psr\Log\LoggerInterface as Logger;
use Psr\Log\NullLogger;

/**
 * All times are milliseconds
 */
class ConnectionSettingsBuilder
{
    /** @var Logger */
    private $log;
    /** @var bool */
    private $verboseLogging = false;
    /** @var int */
    private $maxQueueSize = Consts::DEFAULT_MAX_QUEUE_SIZE;
    /** @var int */
    private $maxConcurrentItems = Consts::DEFAULT_MAX_CONCURRENT_ITEMS;
    /** @var int */
    private $maxRetries = Consts::DEFAULT_MAX_OPERATIONS_RETRY;
    /** @var int */
    private $maxReconnections = Consts::DEFAULT_MAX_RECONNECTIONS;
    /** @var bool */
    private $requireMaster = Consts::DEFAULT_REQUIRE_MASTER;
    /** @var int */
    private $reconnectionDelay = Consts::DEFAULT_RECONNECTION_DELAY;
    /** @var int */
    private $operationTimeout = Consts::DEFAULT_OPERATION_TIMEOUT;
    /** @var int */
    private $operationTimeoutCheckPeriod = Consts::DEFAULT_OPERATION_TIMEOUT_CHECK_PERIOD;
    /** @var UserCredentials|null */
    private $defaultUserCredentials;
    /** @var bool */
    private $useSslConnection = false;
    /** @var string */
    private $targetHost = '';
    /** @var bool */
    private $validateServer = false;
    /** @var bool */
    private $failOnNoServerResponse = true;
    /** @var int */
    private $heartbeatInterval = 750;
    /** @var int */
    private $heartbeatTimeout = 1500;
    /** @var int */
    private $clientConnectionTimeout = 1000;
    /** @var string */
    private $clusterDns = '';
    /** @var int */
    private $maxDiscoverAttempts = Consts::DEFAULT_MAX_CLUSTER_DISCOVER_ATTEMPTS;
    /** @var int */
    private $gossipExternalHttpPort = Consts::DEFAULT_CLUSTER_MANAGER_EXTERNAL_HTTP_PORT;
    /** @var int */
    private $gossipTimeout = 1000;
    /** @var GossipSeed[] */
    private $gossipSeeds = [];
    /** @var bool */
    private $preferRandomNode = false;

    /** @internal */
    public function __construct()
    {
        $this->log = new NullLogger();
    }

    public function useConsoleLogger(): self
    {
        $logHandler = new StreamHandler(new ResourceOutputStream(\STDOUT));
        $logHandler->setFormatter(new ConsoleFormatter("[%datetime%] %channel%.%level_name%: %message%\r\n"));

        $this->log = new MonoLog('event-store-client');
        $this->log->pushHandler($logHandler);

        return $this;
    }

    public function useFileLogger(Handle $handle): self
    {
        $logHandler = new StreamHandler($handle);
        $logHandler->setFormatter(new LineFormatter());

        $this->log = new MonoLog('event-store-client');
        $this->log->pushHandler($logHandler);

        return $this;
    }

    public function useCustomLogger(Logger $logger): self
    {
        $this->log = $logger;

        return $this;
    }

    public function enableVerboseLogging(): self
    {
        $this->verboseLogging = true;

        return $this;
    }

    public function limitOperationsQueueTo(int $limit): self
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('Limit must be positive');
        }

        $this->maxQueueSize = $limit;

        return $this;
    }

    public function limitConcurrentOperationsTo(int $limit): self
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('Limit must be positive');
        }

        $this->maxConcurrentItems = $limit;

        return $this;
    }

    public function limitAttemptsForOperationTo(int $limit): self
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('Limit must be positive');
        }

        $this->maxRetries = $limit - 1;

        return $this;
    }

    public function limitRetriesForOperationTo(int $limit): self
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('Limit must be positive');
        }

        $this->maxRetries = $limit;

        return $this;
    }

    public function keepRetrying(): self
    {
        $this->maxRetries = -1;

        return $this;
    }

    public function limitReconnectionsTo(int $limit): self
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('Limit must be positive');
        }

        $this->maxReconnections = $limit;

        return $this;
    }

    public function keepReconnecting(): self
    {
        $this->maxReconnections = -1;

        return $this;
    }

    public function performOnMasterOnly(): self
    {
        $this->requireMaster = true;

        return $this;
    }

    public function performOnAnyNode(): self
    {
        $this->requireMaster = false;

        return $this;
    }

    public function setReconnectionDelayTo(int $reconnectionDelay): self
    {
        if ($reconnectionDelay < 0) {
            throw new InvalidArgumentException('Delay must be positive');
        }

        $this->reconnectionDelay = $reconnectionDelay;

        return $this;
    }

    public function setOperationTimeoutTo(int $operationTimeout): self
    {
        if ($operationTimeout < 0) {
            throw new InvalidArgumentException('Timeout must be positive');
        }

        $this->operationTimeout = $operationTimeout;

        return $this;
    }

    public function setTimeoutCheckPeriodTo(int $timeoutCheckPeriod): self
    {
        if ($timeoutCheckPeriod < 0) {
            throw new InvalidArgumentException('Timeout must be positive');
        }

        $this->operationTimeoutCheckPeriod = $timeoutCheckPeriod;

        return $this;
    }

    public function setDefaultUserCredentials(UserCredentials $userCredentials): self
    {
        $this->defaultUserCredentials = $userCredentials;

        return $this;
    }

    public function useSslConnection(string $targetHost, bool $validateServer): self
    {
        if (empty($targetHost)) {
            throw new InvalidArgumentException('Target host required');
        }

        $this->useSslConnection = true;
        $this->targetHost = $targetHost;
        $this->validateServer = $validateServer;

        return $this;
    }

    public function failOnNoServerResponse(): self
    {
        $this->failOnNoServerResponse = true;

        return $this;
    }

    public function setHeartbeatInterval(int $interval): self
    {
        if ($interval < 0) {
            throw new InvalidArgumentException('Interval must be positive');
        }

        $this->heartbeatInterval = $interval;

        return $this;
    }

    public function setHeartbeatTimeout(int $timeout): self
    {
        if ($timeout < 0) {
            throw new InvalidArgumentException('Timeout must be positive');
        }

        $this->heartbeatTimeout = $timeout;

        return $this;
    }

    public function withConnectionTimeoutOf(int $timeout): self
    {
        if ($timeout < 0) {
            throw new InvalidArgumentException('Timeout must be positive');
        }

        $this->clientConnectionTimeout = $timeout;

        return $this;
    }

    public function setClusterDns(string $clusterDns): self
    {
        if (empty($clusterDns)) {
            throw new InvalidArgumentException('Cluster DNS required');
        }

        $this->clusterDns = $clusterDns;

        return $this;
    }

    public function setMaxDiscoverAttempts(int $maxDiscoverAttempts): self
    {
        if ($maxDiscoverAttempts <= 0) {
            throw new InvalidArgumentException('Max discover attempts is out of range');
        }

        $this->maxDiscoverAttempts = $maxDiscoverAttempts;

        return $this;
    }

    public function setGossipTimeout(int $timeout): self
    {
        if ($timeout < 0) {
            throw new InvalidArgumentException('Timeout must be positive');
        }

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
            throw new InvalidArgumentException('Invalid port given');
        }

        $this->gossipExternalHttpPort = $clusterGossipPort;

        return $this;
    }

    /** @param EndPoint[] */
    public function setGossipSeedEndPoints(array $gossipSeeds): self
    {
        if (empty($gossipSeeds)) {
            throw new InvalidArgumentException('Empty FakeDnsEntries collection');
        }

        foreach ($gossipSeeds as $seed) {
            if (! $seed instanceof EndPoint) {
                throw new InvalidArgumentException('Gossip seeds must be an array of ' . EndPoint::class);
            }

            $this->gossipSeeds[] = new GossipSeed($seed);
        }

        return $this;
    }

    /** @param GossipSeed[] */
    public function setGossipSeeds(array $gossipSeeds): self
    {
        if (empty($gossipSeeds)) {
            throw new InvalidArgumentException('Empty FakeDnsEntries collection');
        }

        foreach ($gossipSeeds as $seed) {
            if (! $seed instanceof GossipSeed) {
                throw new InvalidArgumentException('Gossip seeds must be an array of ' . GossipSeed::class);
            }

            $this->gossipSeeds[] = $seed;
        }

        return $this;
    }

    public function build(): ConnectionSettings
    {
        return new ConnectionSettings(
            $this->log,
            $this->verboseLogging,
            $this->maxQueueSize,
            $this->maxConcurrentItems,
            $this->maxRetries,
            $this->maxReconnections,
            $this->requireMaster,
            $this->reconnectionDelay,
            $this->operationTimeout,
            $this->operationTimeoutCheckPeriod,
            $this->defaultUserCredentials,
            $this->useSslConnection,
            $this->targetHost,
            $this->validateServer,
            $this->failOnNoServerResponse,
            $this->heartbeatInterval,
            $this->heartbeatTimeout,
            $this->clusterDns,
            $this->maxDiscoverAttempts,
            $this->gossipExternalHttpPort,
            $this->gossipSeeds,
            $this->gossipTimeout,
            $this->preferRandomNode,
            $this->clientConnectionTimeout
        );
    }
}
