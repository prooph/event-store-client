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

namespace Prooph\EventStoreClient\Internal;

use Amp\Deferred;
use Amp\Promise;
use Prooph\EventStoreClient\ClientOperations\CommitTransactionOperation;
use Prooph\EventStoreClient\ClientOperations\StartTransactionOperation;
use Prooph\EventStoreClient\ClientOperations\TransactionalWriteOperation;
use Prooph\EventStoreClient\ClusterSettings;
use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStoreClient\EventReadResult;
use Prooph\EventStoreClient\EventStoreAsyncConnection;
use Prooph\EventStoreClient\EventStoreSyncConnection;
use Prooph\EventStoreClient\EventStoreSyncTransaction;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\PersistentSubscriptionSettings;
use Prooph\EventStoreClient\Position;
use Prooph\EventStoreClient\StreamEventsSlice;
use Prooph\EventStoreClient\StreamMetadata;
use Prooph\EventStoreClient\StreamMetadataResult;
use Prooph\EventStoreClient\SystemSettings;
use Prooph\EventStoreClient\UserCredentials;
use Prooph\EventStoreClient\WriteResult;

/** @internal */
final class EventStoreSyncNodeConnection implements
    EventStoreSyncConnection,
    EventStoreSyncTransactionConnection
{
    /** @var EventStoreAsyncConnection */
    private $asyncConnection;

    public function __construct(EventStoreAsyncConnection $asyncConnection)
    {
        $this->asyncConnection = $asyncConnection;
    }

    public function connectionName(): string
    {
        return $this->asyncConnection->connectionName();
    }

    public function connectionSettings(): ConnectionSettings
    {
        return $this->asyncConnection->connectionSettings();
    }

    public function clusterSettings(): ?ClusterSettings
    {
        return $this->asyncConnection->clusterSettings();
    }

    /** @throws \Throwable */
    public function connect(): void
    {
        Promise\wait($this->asyncConnection->connectAsync());
    }

    public function close(): void
    {
        $this->asyncConnection->close();
    }

    /** @throws \Throwable */
    public function deleteStream(
        string $stream,
        int $expectedVersion,
        bool $hardDelete,
        UserCredentials $userCredentials = null
    ): void {
        Promise\wait($this->asyncConnection->deleteStreamAsync($stream, $expectedVersion, $hardDelete, $userCredentials));
    }

    /** @throws \Throwable */
    public function appendToStream(
        string $stream,
        int $expectedVersion,
        array $events = [],
        UserCredentials $userCredentials = null
    ): WriteResult {
        return Promise\wait($this->asyncConnection->appendToStreamAsync(
            $stream,
            $expectedVersion,
            $events,
            $userCredentials
        ));
    }

    /** @throws \Throwable */
    public function readEvent(
        string $stream,
        int $eventNumber,
        bool $resolveLinkTo = true,
        UserCredentials $userCredentials = null
    ): EventReadResult {
        return Promise\wait($this->asyncConnection->readEventAsync(
            $stream,
            $eventNumber,
            $resolveLinkTo,
            $userCredentials
        ));
    }

    /** @throws \Throwable */
    public function readStreamEventsForward(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSlice {
        return Promise\wait($this->asyncConnection->readStreamEventsForwardAsync(
            $stream,
            $start,
            $count,
            $resolveLinkTos,
            $userCredentials
        ));
    }

    /** @throws \Throwable */
    public function readStreamEventsBackward(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSlice {
        return Promise\wait($this->asyncConnection->readStreamEventsBackwardAsync(
            $stream,
            $start,
            $count,
            $resolveLinkTos,
            $userCredentials
        ));
    }

    /** @throws \Throwable */
    public function readAllEventsForward(
        Position $position,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSlice {
        return Promise\wait($this->asyncConnection->readAllEventsForwardAsync(
            $position,
            $count,
            $resolveLinkTos,
            $userCredentials
        ));
    }

    /** @throws \Throwable */
    public function readAllEventsBackward(
        Position $position,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): StreamEventsSlice {
        return Promise\wait($this->asyncConnection->readAllEventsBackwardAsync(
            $position,
            $count,
            $resolveLinkTos,
            $userCredentials
        ));
    }

    /** @throws \Throwable */
    public function setStreamMetadata(
        string $stream,
        int $expectedMetaStreamVersion,
        StreamMetadata $metadata,
        UserCredentials $userCredentials = null
    ): WriteResult {
        return Promise\wait($this->asyncConnection->setStreamMetadataAsync(
            $stream,
            $expectedMetaStreamVersion,
            $metadata,
            $userCredentials
        ));
    }

    /** @throws \Throwable */
    public function getStreamMetadata(string $stream, UserCredentials $userCredentials = null): StreamMetadataResult
    {
        return Promise\wait($this->asyncConnection->getStreamMetadataAsync($stream, $userCredentials));
    }

    /** @throws \Throwable */
    public function setSystemSettings(SystemSettings $settings, UserCredentials $userCredentials = null): WriteResult
    {
        return Promise\wait($this->asyncConnection->setSystemSettingsAsync($settings, $userCredentials));
    }

    /** @throws \Throwable */
    public function createPersistentSubscription(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        UserCredentials $userCredentials = null
    ): PersistentSubscriptionCreateResult {
        return Promise\wait($this->asyncConnection->createPersistentSubscriptionAsync($stream, $groupName, $settings, $userCredentials));
    }

    /** @throws \Throwable */
    public function updatePersistentSubscription(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        UserCredentials $userCredentials = null
    ): PersistentSubscriptionUpdateResult {
        return Promise\wait($this->asyncConnection->updatePersistentSubscriptionAsync(
            $stream,
            $groupName,
            $settings,
            $userCredentials
        ));
    }

    /** @throws \Throwable */
    public function deletePersistentSubscription(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): PersistentSubscriptionDeleteResult {
        return Promise\wait($this->asyncConnection->deletePersistentSubscriptionAsync($stream, $groupName, $userCredentials));
    }

    /** @throws \Throwable */
    public function connectToPersistentSubscription(
        string $stream,
        string $groupName,
        callable $eventAppeared,
        callable $subscriptionDropped = null,
        int $bufferSize = 10,
        bool $autoAck = true,
        UserCredentials $userCredentials = null
    ): EventStorePersistentSubscription {
        return $this->asyncConnection->connectToPersistentSubscription(
            $stream,
            $groupName,
            $eventAppeared,
            $subscriptionDropped,
            $bufferSize,
            $autoAck,
            $userCredentials
        );
    }

    public function startTransaction(
        string $stream,
        int $expectedVersion,
        UserCredentials $userCredentials = null
    ): EventStoreSyncTransaction {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        $deferred = new Deferred();

        $reflectionMethod = new \ReflectionMethod(\get_class($this->asyncConnection), 'enqueueOperation');
        $reflectionMethod->setAccessible(true);

        $reflectionMethod->invoke($this->asyncConnection, new StartTransactionOperation(
            $this->connectionSettings()->log(),
            $deferred,
            $this->asyncConnection->connectionSettings()->requireMaster(),
            $stream,
            $expectedVersion,
            $this,
            $userCredentials
        ));

        return Promise\wait($deferred->promise());
    }

    /** @throws \Throwable */
    public function continueTransaction(
        int $transactionId,
        UserCredentials $userCredentials = null
    ): EventStoreSyncTransaction {
        if ($transactionId < 0) {
            throw new InvalidArgumentException('Invalid transaction id');
        }

        return new EventStoreSyncTransaction($transactionId, $userCredentials, $this);
    }

    public function transactionalWrite(
        EventStoreSyncTransaction $transaction,
        array $events,
        UserCredentials $userCredentials = null
    ): void {
        if (empty($events)) {
            throw new InvalidArgumentException('No events given');
        }

        $deferred = new Deferred();

        $reflectionMethod = new \ReflectionMethod(\get_class($this->asyncConnection), 'enqueueOperation');
        $reflectionMethod->setAccessible(true);

        $reflectionMethod->invoke($this->asyncConnection, new TransactionalWriteOperation(
            $this->connectionSettings()->log(),
            $deferred,
            $this->asyncConnection->connectionSettings()->requireMaster(),
            $transaction->transactionId(),
            $events,
            $userCredentials
        ));

        Promise\wait($deferred->promise());
    }

    public function commitTransaction(
        EventStoreSyncTransaction $transaction,
        UserCredentials $userCredentials = null
    ): WriteResult {
        $deferred = new Deferred();

        $reflectionMethod = new \ReflectionMethod(\get_class($this->asyncConnection), 'enqueueOperation');
        $reflectionMethod->setAccessible(true);

        $reflectionMethod->invoke($this->asyncConnection, new CommitTransactionOperation(
            $this->connectionSettings()->log(),
            $deferred,
            $this->asyncConnection->connectionSettings()->requireMaster(),
            $transaction->transactionId(),
            $userCredentials
        ));

        return Promise\wait($deferred->promise());
    }

    public function onConnected(callable $handler): ListenerHandler
    {
        return $this->asyncConnection->onConnected($handler);
    }

    public function onDisconnected(callable $handler): ListenerHandler
    {
        return $this->asyncConnection->onDisconnected($handler);
    }

    public function onReconnecting(callable $handler): ListenerHandler
    {
        return $this->asyncConnection->onReconnecting($handler);
    }

    public function onClosed(callable $handler): ListenerHandler
    {
        return $this->asyncConnection->onClosed($handler);
    }

    public function onErrorOccurred(callable $handler): ListenerHandler
    {
        return $this->asyncConnection->onErrorOccurred($handler);
    }

    public function onAuthenticationFailed(callable $handler): ListenerHandler
    {
        return $this->asyncConnection->onAuthenticationFailed($handler);
    }

    public function detach(ListenerHandler $handler): void
    {
        $this->asyncConnection->detach($handler);
    }
}
