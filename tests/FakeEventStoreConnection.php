<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\Promise;
use Amp\Success;
use Prooph\EventStore\AsyncCatchUpSubscriptionDropped;
use Prooph\EventStore\AsyncEventStoreConnection;
use Prooph\EventStore\AsyncEventStorePersistentSubscription;
use Prooph\EventStore\AsyncEventStoreTransaction;
use Prooph\EventStore\AsyncPersistentSubscriptionDropped;
use Prooph\EventStore\CatchUpSubscriptionSettings;
use Prooph\EventStore\ClientAuthenticationFailedEventArgs;
use Prooph\EventStore\ClientClosedEventArgs;
use Prooph\EventStore\ClientConnectionEventArgs;
use Prooph\EventStore\ClientErrorEventArgs;
use Prooph\EventStore\ClientReconnectingEventArgs;
use Prooph\EventStore\ClusterSettings;
use Prooph\EventStore\ConnectionSettings;
use Prooph\EventStore\EventAppearedOnAsyncCatchupSubscription;
use Prooph\EventStore\EventAppearedOnAsyncPersistentSubscription;
use Prooph\EventStore\EventAppearedOnSubscription;
use Prooph\EventStore\Internal\EventHandler;
use Prooph\EventStore\ListenerHandler;
use Prooph\EventStore\LiveProcessingStartedOnAsyncCatchUpSubscription;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\Position;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\SubscriptionDropped;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\UserCredentials;

/** @internal */
class FakeEventStoreConnection implements AsyncEventStoreConnection
{
    /** @var callable */
    private $readAllEventsForwardAsync;
    /** @var callable */
    private $readStreamEventsForwardAsync;
    /** @var callable */
    private $subscribeToStreamAsync;
    /** @var callable */
    private $subscribeToAllAsync;
    /** @var EventHandler */
    private $eventHandler;

    public function __construct()
    {
        $this->readAllEventsForwardAsync = function (Position $position, int $start, int $count, ?UserCredentials $credentials): Promise {
            return new Success();
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
    ): AsyncEventStoreTransaction {
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
        EventAppearedOnAsyncCatchupSubscription $eventAppeared,
        ?LiveProcessingStartedOnAsyncCatchUpSubscription $liveProcessingStarted = null,
        ?AsyncCatchUpSubscriptionDropped $subscriptionDropped = null,
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
        EventAppearedOnAsyncCatchupSubscription $eventAppeared,
        ?LiveProcessingStartedOnAsyncCatchUpSubscription $liveProcessingStarted = null,
        ?AsyncCatchUpSubscriptionDropped $subscriptionDropped = null,
        ?UserCredentials $userCredentials = null
    ): Promise {
        throw new \RuntimeException('Not implemented');
    }

    public function connectToPersistentSubscription(
        string $stream,
        string $groupName,
        EventAppearedOnAsyncPersistentSubscription $eventAppeared,
        ?AsyncPersistentSubscriptionDropped $subscriptionDropped = null,
        int $bufferSize = 10,
        bool $autoAck = true,
        ?UserCredentials $userCredentials = null
    ): AsyncEventStorePersistentSubscription {
        throw new \RuntimeException('Not implemented');
    }

    public function connectToPersistentSubscriptionAsync(
        string $stream,
        string $groupName,
        EventAppearedOnAsyncPersistentSubscription $eventAppeared,
        ?AsyncPersistentSubscriptionDropped $subscriptionDropped = null,
        int $bufferSize = 10,
        bool $autoAck = true,
        ?UserCredentials $userCredentials = null
    ): Promise {
        throw new \RuntimeException('Not implemented');
    }

    public function onConnected(callable $handler): ListenerHandler
    {
        return $this->eventHandler->whenConnected($handler);
    }

    public function onDisconnected(callable $handler): ListenerHandler
    {
        return $this->eventHandler->whenDisconnected($handler);
    }

    public function onReconnecting(callable $handler): ListenerHandler
    {
        return $this->eventHandler->whenReconnecting($handler);
    }

    public function onClosed(callable $handler): ListenerHandler
    {
        return $this->eventHandler->whenClosed($handler);
    }

    public function onErrorOccurred(callable $handler): ListenerHandler
    {
        return $this->eventHandler->whenErrorOccurred($handler);
    }

    public function onAuthenticationFailed(callable $handler): ListenerHandler
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

    public function handleReadStreamEventsForwardAsync(callable $callback): void
    {
        $this->readStreamEventsForwardAsync = $callback;
    }

    public function handleReadAllEventsForwardAsync(callable $callback): void
    {
        $this->readAllEventsForwardAsync = $callback;
    }

    public function handleSubscribeToStreamAsync(callable $callback): void
    {
        $this->subscribeToStreamAsync = $callback;
    }

    public function handleSubscribeToAllAsync(callable $callback): void
    {
        $this->subscribeToAllAsync = $callback;
    }
}
