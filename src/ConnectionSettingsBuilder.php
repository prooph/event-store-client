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

use Amp\ByteStream\WritableResourceStream;
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

    private int $maxQueueSize = Consts::DefaultMaxQueueSize;

    private int $maxConcurrentItems = Consts::DefaultMaxConcurrentItems;

    private int $maxRetries = Consts::DefaultMaxOperationsRetry;

    private int $maxReconnections = Consts::DefaultMaxReconnections;

    private bool $requireMaster = Consts::DefaultRequireMaster;

    private float $reconnectionDelay = Consts::DefaultReconnectionDelay;

    private float $operationTimeout = Consts::DefaultOperationTimeout;

    private float $operationTimeoutCheckPeriod = Consts::DefaultOperationTimeoutPeriod;

    private ?UserCredentials $defaultUserCredentials = null;

    private bool $useSslConnection = false;

    private string $targetHost = '';

    private bool $validateServer = true;

    private bool $failOnNoServerResponse = true;

    private float $heartbeatInterval = 0.75;

    private float $heartbeatTimeout = 1.5;

    private float $clientConnectionTimeout = 1;

    private string $clusterDns = '';

    private int $maxDiscoverAttempts = Consts::DefaultMaxClusterDiscoverAttempts;

    private int $gossipExternalHttpPort = Consts::DefaultClusterManagerExternalHttpPort;

    private float $gossipTimeout = 1;

    /** @var list<GossipSeed> */
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

        $logHandler = new StreamHandler(new WritableResourceStream(\STDOUT));
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

    public function setReconnectionDelayTo(float $reconnectionDelay): self
    {
        if ($reconnectionDelay < 0) {
            throw new InvalidArgumentException('Delay must be positive');
        }

        $this->reconnectionDelay = $reconnectionDelay;

        return $this;
    }

    public function setOperationTimeoutTo(float $operationTimeout): self
    {
        if ($operationTimeout < 0) {
            throw new InvalidArgumentException('Timeout must be positive');
        }

        $this->operationTimeout = $operationTimeout;

        return $this;
    }

    public function setTimeoutCheckPeriodTo(float $timeoutCheckPeriod): self
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

    public function setHeartbeatInterval(float $interval): self
    {
        if ($interval < 0) {
            throw new InvalidArgumentException('Interval must be positive');
        }

        $this->heartbeatInterval = $interval;

        return $this;
    }

    public function setHeartbeatTimeout(float $timeout): self
    {
        if ($timeout < 0) {
            throw new InvalidArgumentException('Timeout must be positive');
        }

        $this->heartbeatTimeout = $timeout;

        return $this;
    }

    public function withConnectionTimeoutOf(float $timeout): self
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

    public function setGossipTimeout(float $timeout): self
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

    /** @param list<EndPoint> $gossipSeeds */
    public function setGossipSeedEndPoints(array $gossipSeeds, bool $seedOverTls = true): self
    {
        if (empty($gossipSeeds)) {
            throw new InvalidArgumentException('Empty FakeDnsEntries collection');
        }

        foreach ($gossipSeeds as $seed) {
            $this->gossipSeeds[] = new GossipSeed($seed, '', $seedOverTls);
        }

        return $this;
    }

    /** @param list<GossipSeed> $gossipSeeds */
    public function setGossipSeeds(array $gossipSeeds): self
    {
        if (empty($gossipSeeds)) {
            throw new InvalidArgumentException('Empty FakeDnsEntries collection');
        }

        $this->gossipSeeds = $gossipSeeds;

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
