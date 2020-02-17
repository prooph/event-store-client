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

namespace Prooph\EventStoreClient\Internal;

use Amp\Deferred;
use Amp\Promise;
use Closure;
use Prooph\EventStore\Async\CatchUpSubscriptionDropped;
use Prooph\EventStore\Async\EventAppearedOnCatchupSubscription;
use Prooph\EventStore\Async\EventAppearedOnPersistentSubscription;
use Prooph\EventStore\Async\EventAppearedOnSubscription;
use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\Async\EventStoreTransaction;
use Prooph\EventStore\Async\Internal\EventStoreTransactionConnection;
use Prooph\EventStore\Async\LiveProcessingStartedOnCatchUpSubscription;
use Prooph\EventStore\Async\PersistentSubscriptionDropped;
use Prooph\EventStore\CatchUpSubscriptionSettings;
use Prooph\EventStore\Common\SystemEventTypes;
use Prooph\EventStore\Common\SystemStreams;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventReadResult;
use Prooph\EventStore\EventReadStatus;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Exception\InvalidOperationException;
use Prooph\EventStore\Exception\MaxQueueSizeLimitReached;
use Prooph\EventStore\Exception\OutOfRangeException;
use Prooph\EventStore\Exception\UnexpectedValueException;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Internal\Consts;
use Prooph\EventStore\ListenerHandler;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\Position;
use Prooph\EventStore\RawStreamMetadataResult;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\StreamMetadataResult;
use Prooph\EventStore\SubscriptionDropped;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\Util\Guid;
use Prooph\EventStore\Util\Json;
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
use Throwable;

final class EventStoreNodeConnection implements
    EventStoreConnection,
    EventStoreTransactionConnection
{
    private string $connectionName;
    private ConnectionSettings $settings;
    private ?ClusterSettings $clusterSettings;
    private EndPointDiscoverer $endPointDiscoverer;
    private EventStoreConnectionLogicHandler $handler;

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

    public function connectAsync(): Promise
    {
        $deferred = new Deferred();
        $this->handler->enqueueMessage(new StartConnectionMessage($deferred, $this->endPointDiscoverer));

        return $deferred->promise();
    }

    public function close(): void
    {
        $this->handler->enqueueMessage(new CloseConnectionMessage('Connection close requested by client'));
    }

    public function deleteStreamAsync(
        string $stream,
        int $expectedVersion,
        bool $hardDelete = false,
        ?UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        $deferred = new Deferred();

        $this->enqueueOperation(new DeleteStreamOperation(
            $this->settings->log(),
            $deferred,
            $this->settings->requireMaster(),
            $stream,
            $expectedVersion,
            $hardDelete,
            $userCredentials
        ));

        return $deferred->promise();
    }

    /** {@inheritdoc} */
    public function appendToStreamAsync(
        string $stream,
        int $expectedVersion,
        array $events = [],
        ?UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        $deferred = new Deferred();

        $this->enqueueOperation(new AppendToStreamOperation(
            $this->settings->log(),
            $deferred,
            $this->settings->requireMaster(),
            $stream,
            $expectedVersion,
            $events,
            $userCredentials
        ));

        return $deferred->promise();
    }

    /** {@inheritdoc} */
    public function conditionalAppendToStreamAsync(
        string $stream,
        int $expectedVersion,
        array $events = [],
        ?UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        $deferred = new Deferred();

        $this->enqueueOperation(new ConditionalAppendToStreamOperation(
            $this->settings->log(),
            $deferred,
            $this->settings->requireMaster(),
            $stream,
            $expectedVersion,
            $events,
            $userCredentials
        ));

        return $deferred->promise();
    }

    public function readEventAsync(
        string $stream,
        int $eventNumber,
        bool $resolveLinkTos = true,
        ?UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if ($eventNumber < -1) {
            throw new OutOfRangeException('Event number is out of range');
        }

        $deferred = new Deferred();

        $this->enqueueOperation(new ReadEventOperation(
            $this->settings->log(),
            $deferred,
            $this->settings->requireMaster(),
            $stream,
            $eventNumber,
            $resolveLinkTos,
            $userCredentials
        ));

        return $deferred->promise();
    }

    public function readStreamEventsForwardAsync(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        ?UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if ($start < 0) {
            throw new InvalidArgumentException('Start must be positive');
        }

        if ($count < 1) {
            throw new InvalidArgumentException('Count must be positive');
        }

        if ($count > Consts::MAX_READ_SIZE) {
            throw new InvalidArgumentException(\sprintf(
                'Count should be less than %s. For larger reads you should page.',
                Consts::MAX_READ_SIZE
            ));
        }

        $deferred = new Deferred();

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

        return $deferred->promise();
    }

    public function readStreamEventsBackwardAsync(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        ?UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if ($count < 1) {
            throw new InvalidArgumentException('Count must be positive');
        }

        if ($count > Consts::MAX_READ_SIZE) {
            throw new InvalidArgumentException(\sprintf(
                'Count should be less than %s. For larger reads you should page.',
                Consts::MAX_READ_SIZE
            ));
        }

        $deferred = new Deferred();

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

        return $deferred->promise();
    }

    public function readAllEventsForwardAsync(
        Position $position,
        int $count,
        bool $resolveLinkTos = true,
        ?UserCredentials $userCredentials = null
    ): Promise {
        if ($count < 1) {
            throw new InvalidArgumentException('Count must be positive');
        }

        if ($count > Consts::MAX_READ_SIZE) {
            throw new InvalidArgumentException(\sprintf(
                'Count should be less than %s. For larger reads you should page.',
                Consts::MAX_READ_SIZE
            ));
        }

        $deferred = new Deferred();

        $this->enqueueOperation(new ReadAllEventsForwardOperation(
            $this->settings->log(),
            $deferred,
            $this->settings->requireMaster(),
            $position,
            $count,
            $resolveLinkTos,
            $userCredentials
        ));

        return $deferred->promise();
    }

    public function readAllEventsBackwardAsync(
        Position $position,
        int $count,
        bool $resolveLinkTos = true,
        ?UserCredentials $userCredentials = null
    ): Promise {
        if ($count < 1) {
            throw new InvalidArgumentException('Count must be positive');
        }

        if ($count > Consts::MAX_READ_SIZE) {
            throw new InvalidArgumentException(\sprintf(
                'Count should be less than %s. For larger reads you should page.',
                Consts::MAX_READ_SIZE
            ));
        }

        $deferred = new Deferred();

        $this->enqueueOperation(new ReadAllEventsBackwardOperation(
            $this->settings->log(),
            $deferred,
            $this->settings->requireMaster(),
            $position,
            $count,
            $resolveLinkTos,
            $userCredentials
        ));

        return $deferred->promise();
    }

    public function setStreamMetadataAsync(
        string $stream,
        int $expectedMetaStreamVersion,
        ?StreamMetadata $metadata = null,
        ?UserCredentials $userCredentials = null
    ): Promise {
        $string = $metadata ? Json::encode($metadata) : '';

        return $this->setRawStreamMetadataAsync(
            $stream,
            $expectedMetaStreamVersion,
            $string,
            $userCredentials
        );
    }

    public function setRawStreamMetadataAsync(
        string $stream,
        int $expectedMetaStreamVersion,
        string $metadata = '',
        ?UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if (SystemStreams::isMetastream($stream)) {
            throw new InvalidOperationException(\sprintf(
                'Setting metadata for metastream \'%s\' is not supported',
                $stream
            ));
        }

        $deferred = new Deferred();

        $metaEvent = new EventData(
            null,
            SystemEventTypes::STREAM_METADATA,
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

        return $deferred->promise();
    }

    public function getStreamMetadataAsync(string $stream, ?UserCredentials $userCredentials = null): Promise
    {
        $deferred = new Deferred();

        $promise = $this->getRawStreamMetadataAsync($stream, $userCredentials);
        $promise->onResolve(function (?Throwable $e, ?RawStreamMetadataResult $result) use ($deferred) {
            if (null !== $e) {
                $deferred->fail($e);

                return;
            }

            if (null === $result) {
                $deferred->fail(new UnexpectedValueException(
                    'Expected RawStreamMetadataResult but received null'
                ));

                return;
            }

            if ($result->streamMetadata() === '') {
                $deferred->resolve(new StreamMetadataResult(
                    $result->stream(),
                    $result->isStreamDeleted(),
                    $result->metastreamVersion(),
                    new StreamMetadata()
                ));

                return;
            }

            $metadata = StreamMetadata::createFromArray(Json::decode($result->streamMetadata()));

            $deferred->resolve(new StreamMetadataResult(
                $result->stream(),
                $result->isStreamDeleted(),
                $result->metastreamVersion(),
                $metadata
            ));
        });

        return $deferred->promise();
    }

    public function getRawStreamMetadataAsync(string $stream, ?UserCredentials $userCredentials = null): Promise
    {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        $readEventPromise = $this->readEventAsync(
            SystemStreams::metastreamOf($stream),
            -1,
            false,
            $userCredentials
        );

        $deferred = new Deferred();

        $readEventPromise->onResolve(function (?Throwable $e, $eventReadResult) use ($stream, $deferred) {
            if ($e) {
                $deferred->fail($e);

                return;
            }

            \assert($eventReadResult instanceof EventReadResult);

            switch ($eventReadResult->status()->value()) {
                case EventReadStatus::SUCCESS:
                    $event = $eventReadResult->event();

                    if (null === $event) {
                        throw new UnexpectedValueException('Event is null while operation result is Success');
                    }

                    $event = $event->originalEvent();

                    if (null === $event) {
                        $deferred->resolve(new RawStreamMetadataResult(
                            $stream,
                            false,
                            -1,
                            ''
                        ));

                        break;
                    }

                    $deferred->resolve(new RawStreamMetadataResult(
                        $stream,
                        false,
                        $event->eventNumber(),
                        $event->data()
                    ));
                    break;
                case EventReadStatus::NOT_FOUND:
                case EventReadStatus::NO_STREAM:
                    $deferred->resolve(new RawStreamMetadataResult($stream, false, -1, ''));
                    break;
                case EventReadStatus::STREAM_DELETED:
                    $deferred->resolve(new RawStreamMetadataResult($stream, true, \PHP_INT_MAX, ''));
                    break;
                default:
                    throw new OutOfRangeException(\sprintf(
                        'Unexpected ReadEventResult: %s',
                        $eventReadResult->status()->name()
                    ));
            }
        });

        return $deferred->promise();
    }

    public function setSystemSettingsAsync(SystemSettings $settings, ?UserCredentials $userCredentials = null): Promise
    {
        return $this->appendToStreamAsync(
            SystemStreams::SETTINGS_STREAM,
            ExpectedVersion::ANY,
            [new EventData(null, SystemEventTypes::SETTINGS, true, Json::encode($settings))],
            $userCredentials
        );
    }

    /** {@inheritdoc} */
    public function createPersistentSubscriptionAsync(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        ?UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if (empty($groupName)) {
            throw new InvalidArgumentException('Group cannot be empty');
        }

        $deferred = new Deferred();

        $this->enqueueOperation(new CreatePersistentSubscriptionOperation(
            $this->settings->log(),
            $deferred,
            $stream,
            $groupName,
            $settings,
            $userCredentials
        ));

        return $deferred->promise();
    }

    public function updatePersistentSubscriptionAsync(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        ?UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if (empty($groupName)) {
            throw new InvalidArgumentException('Group cannot be empty');
        }

        $deferred = new Deferred();

        $this->enqueueOperation(new UpdatePersistentSubscriptionOperation(
            $this->settings->log(),
            $deferred,
            $stream,
            $groupName,
            $settings,
            $userCredentials
        ));

        return $deferred->promise();
    }

    public function deletePersistentSubscriptionAsync(
        string $stream,
        string $groupName,
        ?UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if (empty($groupName)) {
            throw new InvalidArgumentException('Group cannot be empty');
        }

        $deferred = new Deferred();

        $this->enqueueOperation(new DeletePersistentSubscriptionOperation(
            $this->settings->log(),
            $deferred,
            $stream,
            $groupName,
            $userCredentials
        ));

        return $deferred->promise();
    }

    /** {@inheritdoc} */
    public function subscribeToStreamAsync(
        string $stream,
        bool $resolveLinkTos,
        EventAppearedOnSubscription $eventAppeared,
        ?SubscriptionDropped $subscriptionDropped = null,
        ?UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        $deferred = new Deferred();

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

        return $deferred->promise();
    }

    /** {@inheritdoc} */
    public function subscribeToStreamFromAsync(
        string $stream,
        ?int $lastCheckpoint,
        ?CatchUpSubscriptionSettings $settings,
        EventAppearedOnCatchupSubscription $eventAppeared,
        ?LiveProcessingStartedOnCatchUpSubscription $liveProcessingStarted = null,
        ?CatchUpSubscriptionDropped $subscriptionDropped = null,
        ?UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if (null === $settings) {
            $settings = CatchUpSubscriptionSettings::default();
        }

        if ($this->settings->verboseLogging()) {
            $settings->verboseLogging();
        }

        return (new EventStoreStreamCatchUpSubscription(
            $this,
            $this->settings->log(),
            $stream,
            $lastCheckpoint,
            $userCredentials,
            $eventAppeared,
            $liveProcessingStarted,
            $subscriptionDropped,
            $settings
        ))->startAsync();
    }

    /** {@inheritdoc} */
    public function subscribeToAllAsync(
        bool $resolveLinkTos,
        EventAppearedOnSubscription $eventAppeared,
        ?SubscriptionDropped $subscriptionDropped = null,
        ?UserCredentials $userCredentials = null
    ): Promise {
        $deferred = new Deferred();

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

        return $deferred->promise();
    }

    public function subscribeToAllFromAsync(
        ?Position $lastCheckpoint,
        ?CatchUpSubscriptionSettings $settings,
        EventAppearedOnCatchupSubscription $eventAppeared,
        ?LiveProcessingStartedOnCatchUpSubscription $liveProcessingStarted = null,
        ?CatchUpSubscriptionDropped $subscriptionDropped = null,
        ?UserCredentials $userCredentials = null
    ): Promise {
        if (null === $settings) {
            $settings = CatchUpSubscriptionSettings::default();
        }

        if ($this->settings->verboseLogging()) {
            $settings->verboseLogging();
        }

        return (new EventStoreAllCatchUpSubscription(
            $this,
            $this->settings->log(),
            $lastCheckpoint,
            $userCredentials,
            $eventAppeared,
            $liveProcessingStarted,
            $subscriptionDropped,
            $settings
        ))->startAsync();
    }

    /** {@inheritdoc} */
    public function connectToPersistentSubscriptionAsync(
        string $stream,
        string $groupName,
        EventAppearedOnPersistentSubscription $eventAppeared,
        ?PersistentSubscriptionDropped $subscriptionDropped = null,
        int $bufferSize = 10,
        bool $autoAck = true,
        ?UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if (empty($groupName)) {
            throw new InvalidArgumentException('Group cannot be empty');
        }

        $subscription = new EventStorePersistentSubscription(
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

        return $subscription->start();
    }

    public function startTransactionAsync(
        string $stream,
        int $expectedVersion,
        ?UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        $deferred = new Deferred();

        $this->enqueueOperation(new StartTransactionOperation(
            $this->settings->log(),
            $deferred,
            $this->settings->requireMaster(),
            $stream,
            $expectedVersion,
            $this,
            $userCredentials
        ));

        return $deferred->promise();
    }

    public function continueTransaction(
        int $transactionId,
        ?UserCredentials $userCredentials = null
    ): EventStoreTransaction {
        if ($transactionId < 0) {
            throw new InvalidArgumentException('Invalid transaction id');
        }

        return new EventStoreTransaction($transactionId, $userCredentials, $this);
    }

    public function transactionalWriteAsync(
        EventStoreTransaction $transaction,
        array $events,
        ?UserCredentials $userCredentials
    ): Promise {
        $deferred = new Deferred();

        $this->enqueueOperation(new TransactionalWriteOperation(
            $this->settings->log(),
            $deferred,
            $this->settings->requireMaster(),
            $transaction->transactionId(),
            $events,
            $userCredentials
        ));

        return $deferred->promise();
    }

    public function commitTransactionAsync(
        EventStoreTransaction $transaction,
        ?UserCredentials $userCredentials
    ): Promise {
        $deferred = new Deferred();

        $this->enqueueOperation(new CommitTransactionOperation(
            $this->settings->log(),
            $deferred,
            $this->settings->requireMaster(),
            $transaction->transactionId(),
            $userCredentials
        ));

        return $deferred->promise();
    }

    public function onConnected(Closure $handler): ListenerHandler
    {
        return $this->handler->onConnected($handler);
    }

    public function onDisconnected(Closure $handler): ListenerHandler
    {
        return $this->handler->onDisconnected($handler);
    }

    public function onReconnecting(Closure $handler): ListenerHandler
    {
        return $this->handler->onReconnecting($handler);
    }

    public function onClosed(Closure $handler): ListenerHandler
    {
        return $this->handler->onClosed($handler);
    }

    public function onErrorOccurred(Closure $handler): ListenerHandler
    {
        return $this->handler->onErrorOccurred($handler);
    }

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

    public function __destruct()
    {
        $this->close();
    }
}
