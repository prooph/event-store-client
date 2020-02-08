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

use Amp\ByteStream\ResourceOutputStream;
use Amp\File\File;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Logger as MonoLog;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStore\Internal\Consts;
use Prooph\EventStore\UserCredentials;
use Psr\Log\LoggerInterface as Logger;
use Psr\Log\NullLogger;

/**
 * All times are milliseconds
 */
class ConnectionSettingsBuilder
{
    private Logger $log;
    private bool $verboseLogging = false;
    private int $maxQueueSize = Consts::DEFAULT_MAX_QUEUE_SIZE;
    private int $maxConcurrentItems = Consts::DEFAULT_MAX_CONCURRENT_ITEMS;
    private int $maxRetries = Consts::DEFAULT_MAX_OPERATIONS_RETRY;
    private int $maxReconnections = Consts::DEFAULT_MAX_RECONNECTIONS;
    private bool $requireMaster = Consts::DEFAULT_REQUIRE_MASTER;
    private int $reconnectionDelay = Consts::DEFAULT_RECONNECTION_DELAY;
    private int $operationTimeout = Consts::DEFAULT_OPERATION_TIMEOUT;
    private int $operationTimeoutCheckPeriod = Consts::DEFAULT_OPERATION_TIMEOUT_CHECK_PERIOD;
    private ?UserCredentials $defaultUserCredentials = null;
    private bool $useSslConnection = false;
    private string $targetHost = '';
    private bool $validateServer = false;
    private bool $failOnNoServerResponse = true;
    private int $heartbeatInterval = 750;
    private int $heartbeatTimeout = 1500;
    private int $clientConnectionTimeout = 1000;
    private string $clusterDns = '';
    private int $maxDiscoverAttempts = Consts::DEFAULT_MAX_CLUSTER_DISCOVER_ATTEMPTS;
    private int $gossipExternalHttpPort = Consts::DEFAULT_CLUSTER_MANAGER_EXTERNAL_HTTP_PORT;
    private int $gossipTimeout = 1000;
    /** @var GossipSeed[] */
    private array $gossipSeeds = [];
    private bool $preferRandomNode = false;

    /** @internal */
    public function __construct()
    {
        $this->log = new NullLogger();
    }

    public function useConsoleLogger(): self
    {
        if (! \class_exists(StreamHandler::class)) {
            throw new RuntimeException(\sprintf(
                '%s not found, install amphp/log ^1.0 via composer to use console logger',
                StreamHandler::class
            ));
        }

        $logHandler = new StreamHandler(new ResourceOutputStream(\STDOUT));
        $logHandler->setFormatter(new ConsoleFormatter("[%datetime%] %channel%.%level_name%: %message%\r\n"));

        $this->log = new MonoLog('event-store-client');
        $this->log->pushHandler($logHandler);

        return $this;
    }

    public function useFileLogger(File $file): self
    {
        if (! \class_exists(StreamHandler::class)) {
            throw new RuntimeException(\sprintf(
                '%s not found, install amphp/log ^1.0 via composer to use file logger',
                StreamHandler::class
            ));
        }

        $logHandler = new StreamHandler($file);
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
        if ($maxDiscoverAttempts < 1) {
            throw new InvalidArgumentException(\sprintf(
                'Max discover attempts is out of range %d. Allowed range: [1, PHP_INT_MAX].',
                $maxDiscoverAttempts
            ));
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

    /** @param EndPoint[] $gossipSeeds */
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

    /** @param GossipSeed[] $gossipSeeds */
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
