<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Amp\Deferred;
use Amp\Promise;
use Prooph\EventStoreClient\CatchUpSubscriptionSettings;
use Prooph\EventStoreClient\ClientOperations\AppendToStreamOperation;
use Prooph\EventStoreClient\ClientOperations\ClientOperation;
use Prooph\EventStoreClient\ClientOperations\CommitTransactionOperation;
use Prooph\EventStoreClient\ClientOperations\CreatePersistentSubscriptionOperation;
use Prooph\EventStoreClient\ClientOperations\DeletePersistentSubscriptionOperation;
use Prooph\EventStoreClient\ClientOperations\DeleteStreamOperation;
use Prooph\EventStoreClient\ClientOperations\ReadAllEventsBackwardOperation;
use Prooph\EventStoreClient\ClientOperations\ReadAllEventsForwardOperation;
use Prooph\EventStoreClient\ClientOperations\ReadEventOperation;
use Prooph\EventStoreClient\ClientOperations\ReadStreamEventsBackwardOperation;
use Prooph\EventStoreClient\ClientOperations\ReadStreamEventsForwardOperation;
use Prooph\EventStoreClient\ClientOperations\StartAsyncTransactionOperation;
use Prooph\EventStoreClient\ClientOperations\TransactionalWriteOperation;
use Prooph\EventStoreClient\ClientOperations\UpdatePersistentSubscriptionOperation;
use Prooph\EventStoreClient\ClusterSettings;
use Prooph\EventStoreClient\Common\SystemEventTypes;
use Prooph\EventStoreClient\Common\SystemStreams;
use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\EventReadResult;
use Prooph\EventStoreClient\EventReadStatus;
use Prooph\EventStoreClient\EventStoreAsyncConnection;
use Prooph\EventStoreClient\EventStoreAsyncTransaction;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Exception\InvalidOperationException;
use Prooph\EventStoreClient\Exception\MaxQueueSizeLimitReachedException;
use Prooph\EventStoreClient\Exception\UnexpectedValueException;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\Internal\Message\CloseConnectionMessage;
use Prooph\EventStoreClient\Internal\Message\StartConnectionMessage;
use Prooph\EventStoreClient\Internal\Message\StartOperationMessage;
use Prooph\EventStoreClient\Internal\Message\StartSubscriptionMessage;
use Prooph\EventStoreClient\PersistentSubscriptionSettings;
use Prooph\EventStoreClient\Position;
use Prooph\EventStoreClient\StreamMetadata;
use Prooph\EventStoreClient\StreamMetadataResult;
use Prooph\EventStoreClient\SystemSettings;
use Prooph\EventStoreClient\UserCredentials;

final class EventStoreAsyncNodeConnection implements
    EventStoreAsyncConnection,
    EventStoreAsyncTransactionConnection
{
    /** @var string */
    private $connectionName;
    /** @var ConnectionSettings */
    private $settings;
    /** @var ClusterSettings|null */
    private $clusterSettings;
    /** @var EndPointDiscoverer */
    private $endPointDiscoverer;
    /** @var EventStoreConnectionLogicHandler */
    private $handler;

    public function __construct(
        ConnectionSettings $settings,
        ?ClusterSettings $clusterSettings,
        EndPointDiscoverer $endPointDiscoverer,
        string $connectionName = null
    ) {
        $this->settings = $settings;
        $this->connectionName = $connectionName ?? UuidGenerator::generate();
        $this->endPointDiscoverer = $endPointDiscoverer;
        $this->handler = new EventStoreConnectionLogicHandler($this, $settings);
    }

    public function connectionSettings(): ConnectionSettings
    {
        return $this->connectionSettings();
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
        bool $hardDelete,
        UserCredentials $userCredentials = null
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

    /**
     * @param string $stream
     * @param int $expectedVersion
     * @param EventData[] $events
     * @param UserCredentials|null $userCredentials
     * @return Promise
     */
    public function appendToStreamAsync(
        string $stream,
        int $expectedVersion,
        array $events,
        UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if (empty($events)) {
            throw new InvalidArgumentException('No events given');
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

    public function readEventAsync(
        string $stream,
        int $eventNumber,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
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
        UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if ($count > Consts::MaxReadSize) {
            throw new InvalidArgumentException(\sprintf(
                'Count should be less than %s. For larger reads you should page.',
                Consts::MaxReadSize
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
        UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if ($count > Consts::MaxReadSize) {
            throw new InvalidArgumentException(\sprintf(
                'Count should be less than %s. For larger reads you should page.',
                Consts::MaxReadSize
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
        UserCredentials $userCredentials = null
    ): Promise {
        if ($count > Consts::MaxReadSize) {
            throw new InvalidArgumentException(\sprintf(
                'Count should be less than %s. For larger reads you should page.',
                Consts::MaxReadSize
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
        UserCredentials $userCredentials = null
    ): Promise {
        if ($count > Consts::MaxReadSize) {
            throw new InvalidArgumentException(\sprintf(
                'Count should be less than %s. For larger reads you should page.',
                Consts::MaxReadSize
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
        ?StreamMetadata $metadata,
        UserCredentials $userCredentials = null
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
            SystemEventTypes::StreamMetadata,
            true,
            $metadata ? \json_encode($metadata->toArray()) : null
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

    public function getStreamMetadataAsync(string $stream, UserCredentials $userCredentials = null): Promise
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

        $readEventPromise->onResolve(function (?\Throwable $e, $eventReadResult) use ($stream, $deferred) {
            if ($e) {
                $deferred->fail($e);
            }

            /** @var EventReadResult $eventReadResult */

            switch ($eventReadResult->status()->value()) {
                case EventReadStatus::Success:
                    $event = $eventReadResult->event();

                    if (null === $event) {
                        throw new UnexpectedValueException('Event is null while operation result is Success');
                    }

                    $event = $event->originalEvent();

                    $deferred->resolve(new StreamMetadataResult(
                        $stream,
                        false,
                        $event ? $event->eventNumber() : -1,
                        $event ? $event->data() : ''
                    ));
                    break;
                case EventReadStatus::NotFound:
                case EventReadStatus::NoStream:
                    $deferred->resolve(new StreamMetadataResult($stream, false, -1, ''));
                    break;
                case EventReadStatus::StreamDeleted:
                    $deferred->resolve(new StreamMetadataResult($stream, true, PHP_INT_MAX, ''));
                    break;
            }
        });

        return $deferred->promise();
    }

    public function setSystemSettingsAsync(SystemSettings $settings, UserCredentials $userCredentials = null): Promise
    {
        return $this->appendToStreamAsync(
            SystemStreams::SettingsStream,
            ExpectedVersion::Any,
            [new EventData(null, SystemEventTypes::Settings, true, \json_encode($settings->toArray()))],
            $userCredentials
        );
    }

    /** {@inheritdoc} */
    public function createPersistentSubscriptionAsync(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        UserCredentials $userCredentials = null
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
        UserCredentials $userCredentials = null
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
        UserCredentials $userCredentials = null
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
        callable $eventAppeared,
        callable $subscriptionDropped = null,
        UserCredentials $userCredentials = null
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
    public function subscribeToStreamFrom(
        string $stream,
        ?int $lastCheckpoint,
        CatchUpSubscriptionSettings $settings = null,
        callable $eventAppeared,
        callable $liveProcessingStarted = null,
        callable $subscriptionDropped = null,
        UserCredentials $userCredentials = null
    ): EventStoreStreamCatchUpSubscription {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if (null === $settings) {
            $settings = CatchUpSubscriptionSettings::default();
        }

        if ($this->settings->verboseLogging()) {
            $settings->verboseLogging();
        }

        $catchUpSubscription = new EventStoreStreamCatchUpSubscription(
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

        $catchUpSubscription->startAsync();

        return $catchUpSubscription;
    }

    /** {@inheritdoc} */
    public function subscribeToAllAsync(
        bool $resolveLinkTos,
        callable $eventAppeared,
        callable $subscriptionDropped = null,
        UserCredentials $userCredentials = null
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

    /** {@inheritdoc} */
    public function subscribeToAllFrom(
        ?Position $lastCheckpoint,
        CatchUpSubscriptionSettings $settings = null,
        callable $eventAppeared,
        callable $liveProcessingStarted = null,
        callable $subscriptionDropped = null,
        UserCredentials $userCredentials = null
    ): EventStoreAllCatchUpSubscription {
        if (null === $settings) {
            $settings = CatchUpSubscriptionSettings::default();
        }

        if ($this->settings->verboseLogging()) {
            $settings->verboseLogging();
        }

        $catchUpSubscription = new EventStoreAllCatchUpSubscription(
            $this,
            $this->settings->log(),
            $lastCheckpoint,
            $userCredentials,
            $eventAppeared,
            $liveProcessingStarted,
            $subscriptionDropped,
            $settings
        );

        $catchUpSubscription->startAsync();

        return $catchUpSubscription;
    }

    /** {@inheritdoc} */
    public function connectToPersistentSubscription(
        string $stream,
        string $groupName,
        callable $eventAppeared,
        callable $subscriptionDropped = null,
        int $bufferSize = 10,
        bool $autoAck = true,
        UserCredentials $userCredentials = null
    ): EventStorePersistentSubscription {
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

        $subscription->start();

        return $subscription;
    }

    /** {@inheritdoc} */
    public function connectToPersistentSubscriptionAsync(
        string $stream,
        string $groupName,
        callable $eventAppeared,
        ?callable $subscriptionDropped,
        int $bufferSize = 10,
        bool $autoAck = true,
        UserCredentials $userCredentials = null
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
        UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        $deferred = new Deferred();

        $this->enqueueOperation(new StartAsyncTransactionOperation(
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
        UserCredentials $userCredentials = null
    ): EventStoreAsyncTransaction {
        if ($transactionId < 0) {
            throw new InvalidArgumentException('Invalid transaction id');
        }

        return new EventStoreAsyncTransaction($transactionId, $userCredentials, $this);
    }

    public function transactionalWriteAsync(
        EventStoreAsyncTransaction $transaction,
        array $events,
        ?UserCredentials $userCredentials
    ): Promise {
        if (empty($events)) {
            throw new InvalidArgumentException('No events given');
        }

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
        EventStoreAsyncTransaction $transaction,
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

    public function onConnected(callable $handler): ListenerHandler
    {
        return $this->handler->onConnected($handler);
    }

    public function onDisconnected(callable $handler): ListenerHandler
    {
        return $this->handler->onDisconnected($handler);
    }

    public function onReconnecting(callable $handler): ListenerHandler
    {
        return $this->handler->onReconnecting($handler);
    }

    public function onClosed(callable $handler): ListenerHandler
    {
        return $this->handler->onClosed($handler);
    }

    public function onErrorOccurred(callable $handler): ListenerHandler
    {
        return $this->handler->onErrorOccurred($handler);
    }

    public function onAuthenticationFailed(callable $handler): ListenerHandler
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
            throw MaxQueueSizeLimitReachedException::with($this->connectionName, $this->settings->maxQueueSize());
        }

        $this->handler->enqueueMessage(new StartOperationMessage(
            $operation,
            $this->settings->maxRetries(),
            $this->settings->operationTimeout()
        ));
    }
}
