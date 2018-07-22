<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

/** @internal */
class Consts
{
    public const DefaultMaxQueueSize = 5000;
    public const DefaultMaxConcurrentItems = 5000;
    public const DefaultMaxOperationRetries = 10;
    public const DefaultMaxReconnections = 10;
    public const DefaultRequireMaster = true;
    public const DefaultReconnectionDelay = 100; // milliseconds
    public const DefaultOperationTimeout = 7000; // milliseconds
    public const DefaultOperationTimeoutCheckPeriod = 1000; // milliseconds
    public const TimerPeriod = 200; // milliseconds
    public const MaxReadSize = 4096;
    public const DefaultMaxClusterDiscoverAttempts = 10;
    public const DefaultClusterManagerExternalHttpPort = 30778;
    public const CatchUpDefaultReadBatchSize = 500;
    public const CatchUpDefaultMaxPushQueueSize = 10000;

    public const TcpPortDefault = 1113;
    public const SecureTcpPortDefault = 0;
    public const HttpPortDefault = 2113;
}
