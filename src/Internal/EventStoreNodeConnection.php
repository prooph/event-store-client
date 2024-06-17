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

namespace Prooph\EventStoreClient\Internal;

use Amp\DeferredFuture;
use Closure;
use Prooph\EventStore\AllEventsSlice;
use Prooph\EventStore\CatchUpSubscriptionSettings;
use Prooph\EventStore\Common\SystemEventTypes;
use Prooph\EventStore\Common\SystemStreams;
use Prooph\EventStore\ConditionalWriteResult;
use Prooph\EventStore\DeleteResult;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventReadResult;
use Prooph\EventStore\EventReadStatus;
use Prooph\EventStore\EventStoreAllCatchUpSubscription;
use Prooph\EventStore\EventStoreConnection;
use Prooph\EventStore\EventStorePersistentSubscription;
use Prooph\EventStore\EventStoreStreamCatchUpSubscription;
use Prooph\EventStore\EventStoreSubscription;
use Prooph\EventStore\EventStoreTransaction;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Exception\InvalidOperationException;
use Prooph\EventStore\Exception\MaxQueueSizeLimitReached;
use Prooph\EventStore\Exception\OutOfRangeException;
use Prooph\EventStore\Exception\UnexpectedValueException;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Internal\Consts;
use Prooph\EventStore\Internal\EventStoreTransactionConnection;
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
use Prooph\EventStore\Util\Guid;
use Prooph\EventStore\Util\Json;
use Prooph\EventStore\WriteResult;
use Prooph\EventStoreClient\ClientOperations\AppendToStreamOperation;
use Prooph\EventStoreClient\ClientOperations\ClientOperation;
use Prooph\EventStoreClient\ClientOperations\CommitTransactionOperation;
use Prooph\EventStoreClient\ClientOperations\ConditionalAppendToStreamOperation;
use Prooph\EventStoreClient\ClientOperations\CreatePersistentSubscriptionOperation;
use Prooph\EventStoreClient\ClientOperations\DeletePersistentSubscriptionOperation;
use Prooph\EventStoreClient\ClientOperations\DeleteStreamOperation;
use Prooph\EventStoreClient\ClientOperations\ReadAllEventsBackwardOperation;
use Prooph\EventStoreClient\ClientOperations\ReadAllEventsForwardOperation;
use Prooph\EventStoreClient\ClientOperations\ReadEventOperation;
use Prooph\EventStoreClient\ClientOperations\ReadStreamEventsBackwardOperation;
use Prooph\EventStoreClient\ClientOperations\ReadStreamEventsForwardOperation;
use Prooph\EventStoreClient\ClientOperations\StartTransactionOperation;
use Prooph\EventStoreClient\ClientOperations\TransactionalWriteOperation;
use Prooph\EventStoreClient\ClientOperations\UpdatePersistentSubscriptionOperation;
use Prooph\EventStoreClient\ClusterSettings;
use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStoreClient\Internal\Message\CloseConnectionMessage;
use Prooph\EventStoreClient\Internal\Message\StartConnectionMessage;
use Prooph\EventStoreClient\Internal\Message\StartOperationMessage;
use Prooph\EventStoreClient\Internal\Message\StartSubscriptionMessage;

final class EventStoreNodeConnection implements
    EventStoreConnection,
    EventStoreTransactionConnection
{
    private readonly string $connectionName;

    private readonly ConnectionSettings $settings;

    private readonly ?ClusterSettings $clusterSettings;

    private readonly EndPointDiscoverer $endPointDiscoverer;

    private readonly EventStoreConnectionLogicHandler $handler;

    public function __construct(
        ConnectionSettings $settings,
        ?ClusterSettings $clusterSettings,
        EndPointDiscoverer $endPointDiscoverer,
        ?string $connectionName = null
    ) {
        $this->settings = $settings;
        $this->clusterSettings = $clusterSettings;
        $this->connectionName = $connectionName ?? Guid::generateAsHex();
        $this->endPointDiscoverer = $endPointDiscoverer;
        $this->handler = new EventStoreConnectionLogicHandler($this, $settings);
    }

    public function connectionSettings(): ConnectionSettings
    {
        return $this->settings;
    }

    public function clusterSettings(): ?ClusterSettings
    {
        return $this->clusterSettings;
    }

    public function connectionName(): string
    {
        return $this->connectionName;
    }

    public function connect(): void
    {
        $deferred = new DeferredFuture();
        $this->handler->enqueueMessage(new StartConnectionMessage($deferred, $this->endPointDiscoverer));

        $deferred->getFuture()->await();
    }

    public function close(): void
    {
        $this->handler->enqueueMessage(new CloseConnectionMessage('Connection close requested by client'));
    }

    public function deleteStream(
        string $stream,
        int $expectedVersion,
        bool $hardDelete = false,
        ?UserCredentials $userCredentials = null
    ): DeleteResult {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        $deferred = new DeferredFuture();

        $this->enqueueOperation(new DeleteStreamOperation(
            $this->settings->log(),
            $deferred,
            $this->settings->requireMaster(),
            $stream,
            $expectedVersion,
            $hardDelete,
            $userCredentials
        ));

        return $deferred->getFuture()->await();
    }

    public function appendToStream(
        string $stream,
        int $expectedVersion,
        array $events = [],
        ?UserCredentials $userCredentials = null
    ): WriteResult {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        $deferred = new DeferredFuture();

        $this->enqueueOperation(new AppendToStreamOperation(
            $this->settings->log(),
            $deferred,
            $this->settings->requireMaster(),
            $stream,
            $expectedVersion,
            $events,
            $userCredentials
        ));

        return $deferred->getFuture()->await();
    }

    /** @inheritdoc */
    public function conditionalAppendToStream(
        string $stream,
        int $expectedVersion,
        array $events = [],
        ?UserCredentials $userCredentials = null
    ): ConditionalWriteResult {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        $deferred = new DeferredFuture();

        $this->enqueueOperation(new ConditionalAppendToStreamOperation(
            $this->settings->log(),
            $deferred,
            $this->settings->requireMaster(),
            $stream,
            $expectedVersion,
            $events,
            $userCredentials
        ));

        return $deferred->getFuture()->await();
    }

    /** @inheritdoc */
    public function readEvent(
        string $stream,
        int $eventNumber,
        bool $resolveLinkTos = true,
        ?UserCredentials $userCredentials = null
    ): EventReadResult {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if ($eventNumber < -1) {
            throw new OutOfRangeException('Event number is out of range');
        }

        $deferred = new DeferredFuture();

        $this->enqueueOperation(new ReadEventOperation(
            $this->settings->log(),
            $deferred,
            $this->settings->requireMaster(),
            $stream,
            $eventNumber,
            $resolveLinkTos,
            $userCredentials
        ));

        return $deferred->getFuture()->await();
    }

    /** @inheritdoc */
    public function readStreamEventsForward(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        ?UserCredentials $userCredentials = null
    ): StreamEventsSlice {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if ($start < 0) {
            throw new InvalidArgumentException('Start must be positive');
        }

        if ($count < 1) {
            throw new InvalidArgumentException('Count must be positive');
        }

        if ($count > Consts::MaxReadSize) {
            throw new InvalidArgumentException(\sprintf(
                'Count should be less than %s. For larger reads you should page.',
                Consts::MaxReadSize
            ));
        }

        $deferred = new DeferredFuture();

        $this->enqueueOperation(new ReadStreamEventsForwardOperation(
            $this->settings->log(),
            $deferred,
            $this->settings->requireMaster(),
            $stream,
            $start,
            $count,
            $resolveLinkTos,
            $userCredentials
        ));

        return $deferred->getFuture()->await();
    }

    /** @inheritdoc */
    public function readStreamEventsBackward(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        ?UserCredentials $userCredentials = null
    ): StreamEventsSlice {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if ($count < 1) {
            throw new InvalidArgumentException('Count must be positive');
        }

        if ($count > Consts::MaxReadSize) {
            throw new InvalidArgumentException(\sprintf(
                'Count should be less than %s. For larger reads you should page.',
                Consts::MaxReadSize
            ));
        }

        $deferred = new DeferredFuture();

        $this->enqueueOperation(new ReadStreamEventsBackwardOperation(
            $this->settings->log(),
            $deferred,
            $this->settings->requireMaster(),
            $stream,
            $start,
            $count,
            $resolveLinkTos,
            $userCredentials
        ));

        return $deferred->getFuture()->await();
    }

    /** @inheritdoc */
    public function readAllEventsForward(
        Position $position,
        int $count,
        bool $resolveLinkTos = true,
        ?UserCredentials $userCredentials = null
    ): AllEventsSlice {
        if ($count < 1) {
            throw new InvalidArgumentException('Count must be positive');
        }

        if ($count > Consts::MaxReadSize) {
            throw new InvalidArgumentException(\sprintf(
                'Count should be less than %s. For larger reads you should page.',
                Consts::MaxReadSize
            ));
        }

        $deferred = new DeferredFuture();

        $this->enqueueOperation(new ReadAllEventsForwardOperation(
            $this->settings->log(),
            $deferred,
            $this->settings->requireMaster(),
            $position,
            $count,
            $resolveLinkTos,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        ));

        return $deferred->getFuture()->await();
    }

    public function readAllEventsBackward(
        Position $position,
        int $count,
        bool $resolveLinkTos = true,
        ?UserCredentials $userCredentials = null
    ): AllEventsSlice {
        if ($count < 1) {
            throw new InvalidArgumentException('Count must be positive');
        }

        if ($count > Consts::MaxReadSize) {
            throw new InvalidArgumentException(\sprintf(
                'Count should be less than %s. For larger reads you should page.',
                Consts::MaxReadSize
            ));
        }

        $deferred = new DeferredFuture();

        $this->enqueueOperation(new ReadAllEventsBackwardOperation(
            $this->settings->log(),
            $deferred,
            $this->settings->requireMaster(),
            $position,
            $count,
            $resolveLinkTos,
            $userCredentials
        ));

        return $deferred->getFuture()->await();
    }

    public function setStreamMetadata(
        string $stream,
        int $expectedMetaStreamVersion,
        ?StreamMetadata $metadata = null,
        ?UserCredentials $userCredentials = null
    ): WriteResult {
        $string = $metadata ? Json::encode($metadata) : '';

        return $this->setRawStreamMetadata(
            $stream,
            $expectedMetaStreamVersion,
            $string,
            $userCredentials
        );
    }

    public function setRawStreamMetadata(
        string $stream,
        int $expectedMetaStreamVersion,
        string $metadata = '',
        ?UserCredentials $userCredentials = null
    ): WriteResult {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if (SystemStreams::isMetastream($stream)) {
            throw new InvalidOperationException(\sprintf(
                'Setting metadata for metastream \'%s\' is not supported',
                $stream
            ));
        }

        $deferred = new DeferredFuture();

        $metaEvent = new EventData(
            null,
            SystemEventTypes::StreamMetadata->value,
            true,
            $metadata
        );

        $this->enqueueOperation(new AppendToStreamOperation(
            $this->settings->log(),
            $deferred,
            $this->settings->requireMaster(),
            SystemStreams::metastreamOf($stream),
            $expectedMetaStreamVersion,
            [$metaEvent],
            $userCredentials
        ));

        return $deferred->getFuture()->await();
    }

    /** @inheritdoc */
    public function getStreamMetadata(string $stream, ?UserCredentials $userCredentials = null): StreamMetadataResult
    {
        $result = $this->getRawStreamMetadata($stream, $userCredentials);

        return new StreamMetadataResult(
            $result->stream(),
            $result->isStreamDeleted(),
            $result->metastreamVersion(),
            $result->streamMetadata() === ''
                ? new StreamMetadata()
                : StreamMetadata::createFromArray(Json::decode($result->streamMetadata()))
        );
    }

    /** @inheritdoc */
    public function getRawStreamMetadata(string $stream, ?UserCredentials $userCredentials = null): RawStreamMetadataResult
    {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        $eventReadResult = $this->readEvent(
            SystemStreams::metastreamOf($stream),
            -1,
            false,
            $userCredentials
        );

        switch ($eventReadResult->status()) {
            case EventReadStatus::Success:
                $event = $eventReadResult->event();

                if (null === $event) {
                    throw new UnexpectedValueException('Event is null while operation result is Success');
                }

                $event = $event->originalEvent();

                if (null === $event) {
                    return new RawStreamMetadataResult(
                        $stream,
                        false,
                        -1,
                        ''
                    );
                }

                return new RawStreamMetadataResult(
                    $stream,
                    false,
                    $event->eventNumber(),
                    $event->data()
                );

                break;
            case EventReadStatus::NotFound:
            case EventReadStatus::NoStream:
                return new RawStreamMetadataResult($stream, false, -1, '');
            case EventReadStatus::StreamDeleted:
                return new RawStreamMetadataResult($stream, true, \PHP_INT_MAX, '');
        }
    }

    /** @inheritdoc */
    public function setSystemSettings(SystemSettings $settings, ?UserCredentials $userCredentials = null): WriteResult
    {
        return $this->appendToStream(
            SystemStreams::SettingsStream,
            ExpectedVersion::Any,
            [new EventData(null, SystemEventTypes::Settings->value, true, Json::encode($settings))],
            $userCredentials
        );
    }

    public function createPersistentSubscription(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        ?UserCredentials $userCredentials = null
    ): PersistentSubscriptionCreateResult {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if (empty($groupName)) {
            throw new InvalidArgumentException('Group cannot be empty');
        }

        $deferred = new DeferredFuture();

        $this->enqueueOperation(new CreatePersistentSubscriptionOperation(
            $this->settings->log(),
            $deferred,
            $stream,
            $groupName,
            $settings,
            $userCredentials
        ));

        return $deferred->getFuture()->await();
    }

    /** @inheritdoc */
    public function updatePersistentSubscription(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        ?UserCredentials $userCredentials = null
    ): PersistentSubscriptionUpdateResult {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if (empty($groupName)) {
            throw new InvalidArgumentException('Group cannot be empty');
        }

        $deferred = new DeferredFuture();

        $this->enqueueOperation(new UpdatePersistentSubscriptionOperation(
            $this->settings->log(),
            $deferred,
            $stream,
            $groupName,
            $settings,
            $userCredentials
        ));

        return $deferred->getFuture()->await();
    }

    /** @inheritdoc */
    public function deletePersistentSubscription(
        string $stream,
        string $groupName,
        ?UserCredentials $userCredentials = null
    ): PersistentSubscriptionDeleteResult {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if (empty($groupName)) {
            throw new InvalidArgumentException('Group cannot be empty');
        }

        $deferred = new DeferredFuture();

        $this->enqueueOperation(new DeletePersistentSubscriptionOperation(
            $this->settings->log(),
            $deferred,
            $stream,
            $groupName,
            $userCredentials
        ));

        return $deferred->getFuture()->await();
    }

    /** @inheritdoc */
    public function subscribeToStream(
        string $stream,
        bool $resolveLinkTos,
        Closure $eventAppeared,
        ?Closure $subscriptionDropped = null,
        ?UserCredentials $userCredentials = null
    ): EventStoreSubscription {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        $deferred = new DeferredFuture();

        $this->handler->enqueueMessage(new StartSubscriptionMessage(
            $deferred,
            $stream,
            $resolveLinkTos,
            $userCredentials,
            $eventAppeared,
            $subscriptionDropped,
            $this->settings->maxRetries(),
            $this->settings->operationTimeout()
        ));

        return $deferred->getFuture()->await();
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
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if (null === $settings) {
            $settings = CatchUpSubscriptionSettings::default();
        }

        if ($this->settings->verboseLogging()) {
            $settings = $settings->enableVerboseLogging();
        }

        $subscription = new \Prooph\EventStoreClient\Internal\EventStoreStreamCatchUpSubscription(
            $this,
            $this->settings->log(),
            $stream,
            $lastCheckpoint,
            $userCredentials,
            $eventAppeared,
            $liveProcessingStarted,
            $subscriptionDropped,
            $settings
        );

        $subscription->start();

        return $subscription;
    }

    /** @inheritdoc */
    public function subscribeToAll(
        bool $resolveLinkTos,
        Closure $eventAppeared,
        ?Closure $subscriptionDropped = null,
        ?UserCredentials $userCredentials = null
    ): EventStoreSubscription {
        $deferred = new DeferredFuture();

        $this->handler->enqueueMessage(new StartSubscriptionMessage(
            $deferred,
            '',
            $resolveLinkTos,
            $userCredentials,
            $eventAppeared,
            $subscriptionDropped,
            $this->settings->maxRetries(),
            $this->settings->operationTimeout()
        ));

        return $deferred->getFuture()->await();
    }

    /** @inheritdoc */
    public function subscribeToAllFrom(
        ?Position $lastCheckpoint,
        ?CatchUpSubscriptionSettings $settings,
        Closure $eventAppeared,
        ?Closure $liveProcessingStarted = null,
        ?Closure $subscriptionDropped = null,
        ?UserCredentials $userCredentials = null
    ): EventStoreAllCatchUpSubscription {
        if (null === $settings) {
            $settings = CatchUpSubscriptionSettings::default();
        }

        if ($this->settings->verboseLogging()) {
            $settings = $settings->enableVerboseLogging();
        }

        $subscription = new \Prooph\EventStoreClient\Internal\EventStoreAllCatchUpSubscription(
            $this,
            $this->settings->log(),
            $lastCheckpoint,
            $userCredentials,
            $eventAppeared,
            $liveProcessingStarted,
            $subscriptionDropped,
            $settings
        );

        $subscription->start();

        return $subscription;
    }

    /** @inheritdoc */
    public function connectToPersistentSubscription(
        string $stream,
        string $groupName,
        Closure $eventAppeared,
        ?Closure $subscriptionDropped = null,
        int $bufferSize = 10,
        bool $autoAck = true,
        ?UserCredentials $userCredentials = null
    ): EventStorePersistentSubscription {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if (empty($groupName)) {
            throw new InvalidArgumentException('Group cannot be empty');
        }

        $subscription = new \Prooph\EventStoreClient\Internal\EventStorePersistentSubscription(
            $groupName,
            $stream,
            $eventAppeared,
            $subscriptionDropped,
            $userCredentials,
            $this->settings->log(),
            $this->settings->verboseLogging(),
            $this->settings,
            $this->handler,
            $bufferSize,
            $autoAck
        );

        $subscription->start();

        return $subscription;
    }

    /** @inheritdoc */
    public function startTransaction(
        string $stream,
        int $expectedVersion,
        ?UserCredentials $userCredentials = null
    ): EventStoreTransaction {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        $deferred = new DeferredFuture();

        $this->enqueueOperation(new StartTransactionOperation(
            $this->settings->log(),
            $deferred,
            $this->settings->requireMaster(),
            $stream,
            $expectedVersion,
            $this,
            $userCredentials
        ));

        return $deferred->getFuture()->await();
    }

    /** @inheritdoc */
    public function continueTransaction(
        int $transactionId,
        ?UserCredentials $userCredentials = null
    ): EventStoreTransaction {
        if ($transactionId < 0) {
            throw new InvalidArgumentException('Invalid transaction id');
        }

        return new EventStoreTransaction($transactionId, $userCredentials, $this);
    }

    /** @inheritdoc */
    public function transactionalWrite(
        EventStoreTransaction $transaction,
        array $events,
        ?UserCredentials $userCredentials
    ): void {
        $deferred = new DeferredFuture();

        $this->enqueueOperation(new TransactionalWriteOperation(
            $this->settings->log(),
            $deferred,
            $this->settings->requireMaster(),
            $transaction->transactionId(),
            $events,
            $userCredentials
        ));

        $deferred->getFuture()->await();
    }

    /** @inheritdoc */
    public function commitTransaction(
        EventStoreTransaction $transaction,
        ?UserCredentials $userCredentials
    ): WriteResult {
        $deferred = new DeferredFuture();

        $this->enqueueOperation(new CommitTransactionOperation(
            $this->settings->log(),
            $deferred,
            $this->settings->requireMaster(),
            $transaction->transactionId(),
            $userCredentials
        ));

        return $deferred->getFuture()->await();
    }

    /** @inheritdoc */
    public function onConnected(Closure $handler): ListenerHandler
    {
        return $this->handler->onConnected($handler);
    }

    /** @inheritdoc */
    public function onDisconnected(Closure $handler): ListenerHandler
    {
        return $this->handler->onDisconnected($handler);
    }

    /** @inheritdoc */
    public function onReconnecting(Closure $handler): ListenerHandler
    {
        return $this->handler->onReconnecting($handler);
    }

    /** @inheritdoc */
    public function onClosed(Closure $handler): ListenerHandler
    {
        return $this->handler->onClosed($handler);
    }

    /** @inheritdoc */
    public function onErrorOccurred(Closure $handler): ListenerHandler
    {
        return $this->handler->onErrorOccurred($handler);
    }

    /** @inheritdoc */
    public function onAuthenticationFailed(Closure $handler): ListenerHandler
    {
        return $this->handler->onAuthenticationFailed($handler);
    }

    public function detach(ListenerHandler $handler): void
    {
        $this->handler->detach($handler);
    }

    private function enqueueOperation(ClientOperation $operation): void
    {
        if ($this->handler->totalOperationCount() >= $this->settings->maxQueueSize()) {
            throw MaxQueueSizeLimitReached::with($this->connectionName, $this->settings->maxQueueSize());
        }

        $this->handler->enqueueMessage(new StartOperationMessage(
            $operation,
            $this->settings->maxRetries(),
            $this->settings->operationTimeout()
        ));
    }
}
