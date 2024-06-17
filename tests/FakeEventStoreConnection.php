<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2024 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2024 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Closure;
use Prooph\EventStore\AllEventsSlice;
use Prooph\EventStore\CatchUpSubscriptionSettings;
use Prooph\EventStore\ClientAuthenticationFailedEventArgs;
use Prooph\EventStore\ClientClosedEventArgs;
use Prooph\EventStore\ClientConnectionEventArgs;
use Prooph\EventStore\ClientErrorEventArgs;
use Prooph\EventStore\ClientReconnectingEventArgs;
use Prooph\EventStore\ConditionalWriteResult;
use Prooph\EventStore\DeleteResult;
use Prooph\EventStore\EventReadResult;
use Prooph\EventStore\EventStoreAllCatchUpSubscription;
use Prooph\EventStore\EventStoreConnection;
use Prooph\EventStore\EventStorePersistentSubscription;
use Prooph\EventStore\EventStoreStreamCatchUpSubscription;
use Prooph\EventStore\EventStoreSubscription;
use Prooph\EventStore\EventStoreTransaction;
use Prooph\EventStore\Internal\EventHandler;
use Prooph\EventStore\ListenerHandler;
use Prooph\EventStore\PersistentSubscriptionCreateResult;
use Prooph\EventStore\PersistentSubscriptionDeleteResult;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\PersistentSubscriptionUpdateResult;
use Prooph\EventStore\Position;
use Prooph\EventStore\RawStreamMetadataResult;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\StreamMetadataResult;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\WriteResult;
use Prooph\EventStoreClient\ClusterSettings;
use Prooph\EventStoreClient\ConnectionSettings;

/** @internal */
class FakeEventStoreConnection implements EventStoreConnection
{
    private Closure $readAllEventsForward;

    private Closure $readStreamEventsForward;

    private Closure $subscribeToStream;

    private Closure $subscribeToAll;

    private EventHandler $eventHandler;

    public function __construct()
    {
        $this->readAllEventsForward = function (
            Position $position,
            int $start,
            int $count,
            ?UserCredentials $credentials
        ): void {
        };

        $this->eventHandler = new EventHandler();
    }

    public function connectionName(): string
    {
        return '';
    }

    public function connectionSettings(): ConnectionSettings
    {
        return ConnectionSettings::default();
    }

    public function clusterSettings(): ?ClusterSettings
    {
        return null;
    }

    public function connect(): void
    {
        throw new \RuntimeException('Not implemented');
    }

    public function close(): void
    {
        throw new \RuntimeException('Not implemented');
    }

    public function deleteStream(
        string $stream,
        int $expectedVersion,
        bool $hardDelete = false,
        ?UserCredentials $userCredentials = null
    ): DeleteResult {
        throw new \RuntimeException('Not implemented');
    }

    public function appendToStream(
        string $stream,
        int $expectedVersion,
        array $events = [],
        ?UserCredentials $userCredentials = null
    ): WriteResult {
        throw new \RuntimeException('Not implemented');
    }

    public function conditionalAppendToStream(
        string $stream,
        int $expectedVersion,
        array $events = [],
        ?UserCredentials $userCredentials = null
    ): ConditionalWriteResult {
        throw new \RuntimeException('Not implemented');
    }

    public function readEvent(
        string $stream,
        int $eventNumber,
        bool $resolveLinkTos = true,
        ?UserCredentials $userCredentials = null
    ): EventReadResult {
        throw new \RuntimeException('Not implemented');
    }

    public function readStreamEventsForward(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        ?UserCredentials $userCredentials = null
    ): StreamEventsSlice {
        return ($this->readStreamEventsForward)(
            $stream,
            $start,
            $count,
            $resolveLinkTos,
            $userCredentials
        );
    }

    public function readStreamEventsBackward(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        ?UserCredentials $userCredentials = null
    ): StreamEventsSlice {
        throw new \RuntimeException('Not implemented');
    }

    public function readAllEventsForward(
        Position $position,
        int $count,
        bool $resolveLinkTos = true,
        ?UserCredentials $userCredentials = null
    ): AllEventsSlice {
        return ($this->readAllEventsForward)(
            $position,
            $count,
            $resolveLinkTos,
            $userCredentials
        );
    }

    public function readAllEventsBackward(
        Position $position,
        int $count,
        bool $resolveLinkTos = true,
        ?UserCredentials $userCredentials = null
    ): AllEventsSlice {
        throw new \RuntimeException('Not implemented');
    }

    public function setStreamMetadata(
        string $stream,
        int $expectedMetaStreamVersion,
        ?StreamMetadata $metadata = null,
        ?UserCredentials $userCredentials = null
    ): WriteResult {
        throw new \RuntimeException('Not implemented');
    }

    public function setRawStreamMetadata(
        string $stream,
        int $expectedMetaStreamVersion,
        string $metadata = '',
        ?UserCredentials $userCredentials = null
    ): WriteResult {
        throw new \RuntimeException('Not implemented');
    }

    public function getStreamMetadata(string $stream, ?UserCredentials $userCredentials = null): StreamMetadataResult
    {
        throw new \RuntimeException('Not implemented');
    }

    public function getRawStreamMetadata(string $stream, ?UserCredentials $userCredentials = null): RawStreamMetadataResult
    {
        throw new \RuntimeException('Not implemented');
    }

    public function setSystemSettings(SystemSettings $settings, ?UserCredentials $userCredentials = null): WriteResult
    {
        throw new \RuntimeException('Not implemented');
    }

    public function startTransaction(
        string $stream,
        int $expectedVersion,
        ?UserCredentials $userCredentials = null
    ): EventStoreTransaction {
        throw new \RuntimeException('Not implemented');
    }

    public function continueTransaction(
        int $transactionId,
        ?UserCredentials $userCredentials = null
    ): EventStoreTransaction {
        throw new \RuntimeException('Not implemented');
    }

    public function createPersistentSubscription(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        ?UserCredentials $userCredentials = null
    ): PersistentSubscriptionCreateResult {
        throw new \RuntimeException('Not implemented');
    }

    public function updatePersistentSubscription(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        ?UserCredentials $userCredentials = null
    ): PersistentSubscriptionUpdateResult {
        throw new \RuntimeException('Not implemented');
    }

    public function deletePersistentSubscription(
        string $stream,
        string $groupName,
        ?UserCredentials $userCredentials = null
    ): PersistentSubscriptionDeleteResult {
        throw new \RuntimeException('Not implemented');
    }

    public function subscribeToStream(
        string $stream,
        bool $resolveLinkTos,
        Closure $eventAppeared,
        ?Closure $subscriptionDropped = null,
        ?UserCredentials $userCredentials = null
    ): EventStoreSubscription {
        return ($this->subscribeToStream)(
            $stream,
            $eventAppeared,
            $subscriptionDropped,
            $userCredentials
        );
    }

    public function subscribeToStreamFrom(
        string $stream,
        ?int $lastCheckpoint,
        ?CatchUpSubscriptionSettings $settings,
        Closure $eventAppeared,
        ?Closure $liveProcessingStarted = null,
        ?Closure $subscriptionDropped = null,
        ?UserCredentials $userCredentials = null
    ): EventStoreStreamCatchUpSubscription {
        throw new \RuntimeException('Not implemented');
    }

    public function subscribeToAll(
        bool $resolveLinkTos,
        Closure $eventAppeared,
        ?Closure $subscriptionDropped = null,
        ?UserCredentials $userCredentials = null
    ): EventStoreSubscription {
        return ($this->subscribeToAll)(
            $resolveLinkTos,
            $eventAppeared,
            $subscriptionDropped,
            $userCredentials
        );
    }

    public function subscribeToAllFrom(
        ?Position $lastCheckpoint,
        ?CatchUpSubscriptionSettings $settings,
        Closure $eventAppeared,
        ?Closure $liveProcessingStarted = null,
        ?Closure $subscriptionDropped = null,
        ?UserCredentials $userCredentials = null
    ): EventStoreAllCatchUpSubscription {
        throw new \RuntimeException('Not implemented');
    }

    public function connectToPersistentSubscription(
        string $stream,
        string $groupName,
        Closure $eventAppeared,
        ?Closure $subscriptionDropped = null,
        int $bufferSize = 10,
        bool $autoAck = true,
        ?UserCredentials $userCredentials = null
    ): EventStorePersistentSubscription {
        throw new \RuntimeException('Not implemented');
    }

    public function onConnected(Closure $handler): ListenerHandler
    {
        return $this->eventHandler->whenConnected($handler);
    }

    public function onDisconnected(Closure $handler): ListenerHandler
    {
        return $this->eventHandler->whenDisconnected($handler);
    }

    public function onReconnecting(Closure $handler): ListenerHandler
    {
        return $this->eventHandler->whenReconnecting($handler);
    }

    public function onClosed(Closure $handler): ListenerHandler
    {
        return $this->eventHandler->whenClosed($handler);
    }

    public function onErrorOccurred(Closure $handler): ListenerHandler
    {
        return $this->eventHandler->whenErrorOccurred($handler);
    }

    public function onAuthenticationFailed(Closure $handler): ListenerHandler
    {
        return $this->eventHandler->whenAuthenticationFailed($handler);
    }

    public function onConnected2(ClientConnectionEventArgs $args): void
    {
        $this->eventHandler->connected($args);
    }

    public function onDisconnected2(ClientConnectionEventArgs $args): void
    {
        $this->eventHandler->disconnected($args);
    }

    public function onReconnecting2(ClientReconnectingEventArgs $args): void
    {
        $this->eventHandler->reconnecting($args);
    }

    public function onClosed2(ClientClosedEventArgs $args): void
    {
        $this->eventHandler->closed($args);
    }

    public function onErrorOccurred2(ClientErrorEventArgs $args): void
    {
        $this->eventHandler->errorOccurred($args);
    }

    public function onAuthenticationFailed2(ClientAuthenticationFailedEventArgs $args): void
    {
        $this->eventHandler->authenticationFailed($args);
    }

    public function detach(ListenerHandler $handler): void
    {
        $this->eventHandler->detach($handler);
    }

    public function handleReadStreamEventsForward(Closure $callback): void
    {
        $this->readStreamEventsForward = $callback;
    }

    public function handleReadAllEventsForward(Closure $callback): void
    {
        $this->readAllEventsForward = $callback;
    }

    public function handleSubscribeToStream(Closure $callback): void
    {
        $this->subscribeToStream = $callback;
    }

    public function handleSubscribeToAll(Closure $callback): void
    {
        $this->subscribeToAll = $callback;
    }
}
