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

namespace ProophTest\EventStoreClient;

use Amp\Promise;
use Amp\Success;
use Closure;
use Prooph\EventStore\Async\CatchUpSubscriptionDropped;
use Prooph\EventStore\Async\ClientAuthenticationFailedEventArgs;
use Prooph\EventStore\Async\ClientClosedEventArgs;
use Prooph\EventStore\Async\ClientConnectionEventArgs;
use Prooph\EventStore\Async\ClientErrorEventArgs;
use Prooph\EventStore\Async\ClientReconnectingEventArgs;
use Prooph\EventStore\Async\EventAppearedOnCatchupSubscription;
use Prooph\EventStore\Async\EventAppearedOnPersistentSubscription;
use Prooph\EventStore\Async\EventAppearedOnSubscription;
use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\Async\EventStorePersistentSubscription;
use Prooph\EventStore\Async\EventStoreTransaction;
use Prooph\EventStore\Async\Internal\EventHandler;
use Prooph\EventStore\Async\LiveProcessingStartedOnCatchUpSubscription;
use Prooph\EventStore\Async\PersistentSubscriptionDropped;
use Prooph\EventStore\CatchUpSubscriptionSettings;
use Prooph\EventStore\ListenerHandler;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\Position;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\SubscriptionDropped;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\ClusterSettings;
use Prooph\EventStoreClient\ConnectionSettings;

/** @internal */
class FakeEventStoreConnection implements EventStoreConnection
{
    private Closure $readAllEventsForwardAsync;
    private Closure $readStreamEventsForwardAsync;
    private Closure $subscribeToStreamAsync;
    private Closure $subscribeToAllAsync;
    private EventHandler $eventHandler;

    public function __construct()
    {
        $this->readAllEventsForwardAsync = fn (Position $position, int $start, int $count, ?UserCredentials $credentials): Promise => new Success();

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

    public function connectAsync(): Promise
    {
        throw new \RuntimeException('Not implemented');
    }

    public function close(): void
    {
        throw new \RuntimeException('Not implemented');
    }

    public function deleteStreamAsync(
        string $stream,
        int $expectedVersion,
        bool $hardDelete = false,
        ?UserCredentials $userCredentials = null
    ): Promise {
        throw new \RuntimeException('Not implemented');
    }

    public function appendToStreamAsync(
        string $stream,
        int $expectedVersion,
        array $events = [],
        ?UserCredentials $userCredentials = null
    ): Promise {
        throw new \RuntimeException('Not implemented');
    }

    public function conditionalAppendToStreamAsync(
        string $stream,
        int $expectedVersion,
        array $events = [],
        ?UserCredentials $userCredentials = null
    ): Promise {
        throw new \RuntimeException('Not implemented');
    }

    public function readEventAsync(
        string $stream,
        int $eventNumber,
        bool $resolveLinkTos = true,
        ?UserCredentials $userCredentials = null
    ): Promise {
        throw new \RuntimeException('Not implemented');
    }

    public function readStreamEventsForwardAsync(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        ?UserCredentials $userCredentials = null
    ): Promise {
        return ($this->readStreamEventsForwardAsync)(
            $stream,
            $start,
            $count,
            $resolveLinkTos,
            $userCredentials
        );
    }

    public function readStreamEventsBackwardAsync(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        ?UserCredentials $userCredentials = null
    ): Promise {
        throw new \RuntimeException('Not implemented');
    }

    public function readAllEventsForwardAsync(
        Position $position,
        int $count,
        bool $resolveLinkTos = true,
        ?UserCredentials $userCredentials = null
    ): Promise {
        return ($this->readAllEventsForwardAsync)(
            $position,
            $count,
            $resolveLinkTos,
            $userCredentials
        );
    }

    public function readAllEventsBackwardAsync(
        Position $position,
        int $count,
        bool $resolveLinkTos = true,
        ?UserCredentials $userCredentials = null
    ): Promise {
        throw new \RuntimeException('Not implemented');
    }

    public function setStreamMetadataAsync(
        string $stream,
        int $expectedMetaStreamVersion,
        ?StreamMetadata $metadata = null,
        ?UserCredentials $userCredentials = null
    ): Promise {
        throw new \RuntimeException('Not implemented');
    }

    public function setRawStreamMetadataAsync(
        string $stream,
        int $expectedMetaStreamVersion,
        string $metadata = '',
        ?UserCredentials $userCredentials = null
    ): Promise {
        throw new \RuntimeException('Not implemented');
    }

    public function getStreamMetadataAsync(string $stream, ?UserCredentials $userCredentials = null): Promise
    {
        throw new \RuntimeException('Not implemented');
    }

    public function getRawStreamMetadataAsync(string $stream, ?UserCredentials $userCredentials = null): Promise
    {
        throw new \RuntimeException('Not implemented');
    }

    public function setSystemSettingsAsync(SystemSettings $settings, ?UserCredentials $userCredentials = null): Promise
    {
        throw new \RuntimeException('Not implemented');
    }

    public function startTransactionAsync(
        string $stream,
        int $expectedVersion,
        ?UserCredentials $userCredentials = null
    ): Promise {
        throw new \RuntimeException('Not implemented');
    }

    public function continueTransaction(
        int $transactionId,
        ?UserCredentials $userCredentials = null
    ): EventStoreTransaction {
        throw new \RuntimeException('Not implemented');
    }

    public function createPersistentSubscriptionAsync(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        ?UserCredentials $userCredentials = null
    ): Promise {
        throw new \RuntimeException('Not implemented');
    }

    public function updatePersistentSubscriptionAsync(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        ?UserCredentials $userCredentials = null
    ): Promise {
        throw new \RuntimeException('Not implemented');
    }

    public function deletePersistentSubscriptionAsync(
        string $stream,
        string $groupName,
        ?UserCredentials $userCredentials = null
    ): Promise {
        throw new \RuntimeException('Not implemented');
    }

    public function subscribeToStreamAsync(
        string $stream,
        bool $resolveLinkTos,
        EventAppearedOnSubscription $eventAppeared,
        ?SubscriptionDropped $subscriptionDropped = null,
        ?UserCredentials $userCredentials = null
    ): Promise {
        return ($this->subscribeToStreamAsync)(
            $stream,
            $eventAppeared,
            $subscriptionDropped,
            $userCredentials
        );
    }

    public function subscribeToStreamFromAsync(
        string $stream,
        ?int $lastCheckpoint,
        ?CatchUpSubscriptionSettings $settings,
        EventAppearedOnCatchupSubscription $eventAppeared,
        ?LiveProcessingStartedOnCatchUpSubscription $liveProcessingStarted = null,
        ?CatchUpSubscriptionDropped $subscriptionDropped = null,
        ?UserCredentials $userCredentials = null
    ): Promise {
        throw new \RuntimeException('Not implemented');
    }

    public function subscribeToAllAsync(
        bool $resolveLinkTos,
        EventAppearedOnSubscription $eventAppeared,
        ?SubscriptionDropped $subscriptionDropped = null,
        ?UserCredentials $userCredentials = null
    ): Promise {
        return ($this->subscribeToAllAsync)(
            $resolveLinkTos,
            $eventAppeared,
            $subscriptionDropped,
            $userCredentials
        );
    }

    public function subscribeToAllFromAsync(
        ?Position $lastCheckpoint,
        ?CatchUpSubscriptionSettings $settings,
        EventAppearedOnCatchupSubscription $eventAppeared,
        ?LiveProcessingStartedOnCatchUpSubscription $liveProcessingStarted = null,
        ?CatchUpSubscriptionDropped $subscriptionDropped = null,
        ?UserCredentials $userCredentials = null
    ): Promise {
        throw new \RuntimeException('Not implemented');
    }

    public function connectToPersistentSubscription(
        string $stream,
        string $groupName,
        EventAppearedOnPersistentSubscription $eventAppeared,
        ?PersistentSubscriptionDropped $subscriptionDropped = null,
        int $bufferSize = 10,
        bool $autoAck = true,
        ?UserCredentials $userCredentials = null
    ): EventStorePersistentSubscription {
        throw new \RuntimeException('Not implemented');
    }

    public function connectToPersistentSubscriptionAsync(
        string $stream,
        string $groupName,
        EventAppearedOnPersistentSubscription $eventAppeared,
        ?PersistentSubscriptionDropped $subscriptionDropped = null,
        int $bufferSize = 10,
        bool $autoAck = true,
        ?UserCredentials $userCredentials = null
    ): Promise {
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

    public function handleReadStreamEventsForwardAsync(Closure $callback): void
    {
        $this->readStreamEventsForwardAsync = $callback;
    }

    public function handleReadAllEventsForwardAsync(Closure $callback): void
    {
        $this->readAllEventsForwardAsync = $callback;
    }

    public function handleSubscribeToStreamAsync(Closure $callback): void
    {
        $this->subscribeToStreamAsync = $callback;
    }

    public function handleSubscribeToAllAsync(Closure $callback): void
    {
        $this->subscribeToAllAsync = $callback;
    }
}
