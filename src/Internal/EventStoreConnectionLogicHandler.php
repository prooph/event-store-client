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
use Exception;
use Prooph\EventStore\ClientAuthenticationFailedEventArgs;
use Prooph\EventStore\ClientClosedEventArgs;
use Prooph\EventStore\ClientConnectionEventArgs;
use Prooph\EventStore\ClientErrorEventArgs;
use Prooph\EventStore\ClientReconnectingEventArgs;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\EventStoreConnection;
use Prooph\EventStore\EventStoreSubscription;
use Prooph\EventStore\Exception\CannotEstablishConnection;
use Prooph\EventStore\Exception\EventStoreConnectionException;
use Prooph\EventStore\Exception\InvalidOperationException;
use Prooph\EventStore\Exception\ObjectDisposed;
use Prooph\EventStore\Internal\Consts;
use Prooph\EventStore\Internal\EventHandler;
use Prooph\EventStore\ListenerHandler;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SubscriptionDropReason;
use Prooph\EventStore\Util\Guid;
use Prooph\EventStoreClient\ClientOperations\ClientOperation;
use Prooph\EventStoreClient\ClientOperations\ConnectToPersistentSubscriptionOperation;
use Prooph\EventStoreClient\ClientOperations\VolatileSubscriptionOperation;
use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStoreClient\Internal\Message\CloseConnectionMessage;
use Prooph\EventStoreClient\Internal\Message\EstablishTcpConnectionMessage;
use Prooph\EventStoreClient\Internal\Message\HandleTcpPackageMessage;
use Prooph\EventStoreClient\Internal\Message\Message;
use Prooph\EventStoreClient\Internal\Message\StartConnectionMessage;
use Prooph\EventStoreClient\Internal\Message\StartOperationMessage;
use Prooph\EventStoreClient\Internal\Message\StartPersistentSubscriptionMessage;
use Prooph\EventStoreClient\Internal\Message\StartSubscriptionMessage;
use Prooph\EventStoreClient\Internal\Message\TcpConnectionClosedMessage;
use Prooph\EventStoreClient\Internal\Message\TcpConnectionErrorMessage;
use Prooph\EventStoreClient\Internal\Message\TcpConnectionEstablishedMessage;
use Prooph\EventStoreClient\Messages\ClientMessages\IdentifyClient;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Prooph\EventStoreClient\SystemData\TcpFlags;
use Prooph\EventStoreClient\SystemData\TcpPackage;
use Prooph\EventStoreClient\Transport\Tcp\TcpPackageConnection;
use Revolt\EventLoop;
use Throwable;

/** @internal */
class EventStoreConnectionLogicHandler
{
    private const ClientVersion = 1;

    private readonly EventStoreConnection $esConnection;

    private ?TcpPackageConnection $connection = null;

    private readonly ConnectionSettings $settings;

    private ConnectionState $state;

    private ConnectingPhase $connectingPhase;

    /** @psalm-suppress PropertyNotSetInConstructor */
    private EndPointDiscoverer $endPointDiscoverer;

    private readonly MessageHandler $handler;

    private readonly OperationsManager $operations;

    private readonly SubscriptionsManager $subscriptions;

    private readonly EventHandler $eventHandler;

    private StopWatch $stopWatch;

    private string $timerTickWatcherId = '';

    private ?ReconnectionInfo $reconnInfo = null;

    /** @psalm-suppress PropertyNotSetInConstructor */
    private HeartbeatInfo $heartbeatInfo;

    /** @psalm-suppress PropertyNotSetInConstructor */
    private AuthInfo $authInfo;

    /** @psalm-suppress PropertyNotSetInConstructor */
    private IdentifyInfo $identityInfo;

    private bool $wasConnected = false;

    private int $packageNumber = 0;

    private float $lastTimeoutsTimeStamp;

    public function __construct(EventStoreConnection $connection, ConnectionSettings $settings)
    {
        $this->esConnection = $connection;
        $this->settings = $settings;
        $this->state = ConnectionState::Init;
        $this->connectingPhase = ConnectingPhase::Invalid;
        $this->handler = new MessageHandler();
        $this->operations = new OperationsManager($connection->connectionName(), $settings);
        $this->subscriptions = new SubscriptionsManager($connection->connectionName(), $settings);
        $this->eventHandler = new EventHandler();
        $this->stopWatch = StopWatch::startNew();
        // this allows first connection to connect quick
        $this->lastTimeoutsTimeStamp = -$this->settings->operationTimeoutCheckPeriod();

        $this->handler->registerHandler(
            StartConnectionMessage::class,
            function (StartConnectionMessage $message): void {
                $this->startConnection($message->deferred(), $message->endPointDiscoverer());
            }
        );
        $this->handler->registerHandler(
            CloseConnectionMessage::class,
            function (CloseConnectionMessage $message): void {
                $this->closeConnection($message->reason(), $message->exception());
            }
        );

        $this->handler->registerHandler(
            StartOperationMessage::class,
            function (StartOperationMessage $message): void {
                $this->startOperation($message->operation(), $message->maxRetries(), $message->timeout());
            }
        );
        $this->handler->registerHandler(
            StartSubscriptionMessage::class,
            function (StartSubscriptionMessage $message): void {
                $this->startSubscription($message);
            }
        );
        $this->handler->registerHandler(
            StartPersistentSubscriptionMessage::class,
            function (StartPersistentSubscriptionMessage $message): void {
                $this->startPersistentSubscription($message);
            }
        );

        $this->handler->registerHandler(
            EstablishTcpConnectionMessage::class,
            function (EstablishTcpConnectionMessage $message): void {
                $this->establishTcpConnection($message->deferred(), $message->nodeEndPoints());
            }
        );
        $this->handler->registerHandler(
            TcpConnectionEstablishedMessage::class,
            function (TcpConnectionEstablishedMessage $message): void {
                $this->tcpConnectionEstablished($message->tcpPackageConnection());
            }
        );
        $this->handler->registerHandler(
            TcpConnectionErrorMessage::class,
            function (TcpConnectionErrorMessage $message): void {
                $this->tcpConnectionError($message->tcpPackageConnection(), $message->exception());
            }
        );
        $this->handler->registerHandler(
            TcpConnectionClosedMessage::class,
            function (TcpConnectionClosedMessage $message): void {
                $this->tcpConnectionClosed($message->tcpPackageConnection(), $message->exception());
            }
        );
        $this->handler->registerHandler(
            HandleTcpPackageMessage::class,
            function (HandleTcpPackageMessage $message): void {
                $this->handleTcpPackage($message->tcpPackageConnection(), $message->tcpPackage());
            }
        );
    }

    public function totalOperationCount(): int
    {
        return $this->operations->totalOperationCount();
    }

    public function enqueueMessage(Message $message): void
    {
        $this->logDebug(\sprintf('enqueuing message %s', (string) $message));

        $this->handler->handle($message);
    }

    private function startConnection(DeferredFuture $deferred, EndPointDiscoverer $endPointDiscoverer): void
    {
        $this->logDebug('startConnection');

        switch ($this->state) {
            case ConnectionState::Init:
                $this->timerTickWatcherId = EventLoop::repeat(Consts::TimerPeriod, function (): void {
                    $this->timerTick();
                });
                EventLoop::unreference($this->timerTickWatcherId);

                $this->endPointDiscoverer = $endPointDiscoverer;
                $this->state = ConnectionState::Connecting;
                $this->connectingPhase = ConnectingPhase::Reconnecting;
                $this->discoverEndPoint($deferred);

                break;
            case ConnectionState::Connecting:
            case ConnectionState::Connected:
                $deferred->error(new InvalidOperationException(\sprintf(
                    'EventStoreNodeConnection \'%s\' is already active',
                    $this->esConnection->connectionName()
                )));

                break;
            case ConnectionState::Closed:
                $deferred->error(new ObjectDisposed(\sprintf(
                    'EventStoreNodeConnection \'%s\' is closed',
                    $this->esConnection->connectionName()
                )));

                break;
        }
    }

    private function discoverEndPoint(?DeferredFuture $deferred): void
    {
        $this->logDebug('discoverEndPoint');

        if ($this->state !== ConnectionState::Connecting
            || $this->connectingPhase !== ConnectingPhase::Reconnecting
        ) {
            return;
        }

        $this->connectingPhase = ConnectingPhase::EndPointDiscovery;

        try {
            $endpoints = $this->endPointDiscoverer->discover($this->connection?->remoteEndPoint());
        } catch (Throwable $e) {
            $this->enqueueMessage(new CloseConnectionMessage(
                'Failed to resolve TCP end point to which to connect',
                $e
            ));

            $deferred?->error(new CannotEstablishConnection('Cannot resolve target end point'));

            return;
        }

        $this->enqueueMessage(new EstablishTcpConnectionMessage($deferred, $endpoints));
    }

    /** @throws Exception */
    private function closeConnection(string $reason, ?Throwable $exception = null): void
    {
        if ($this->timerTickWatcherId) {
            EventLoop::cancel($this->timerTickWatcherId);
        }

        if ($this->state === ConnectionState::Closed) {
            if ($exception) {
                $this->logDebug('CloseConnection IGNORED because is ESConnection is CLOSED, reason %s, exception %s', $reason, $exception->getMessage());
            } else {
                $this->logDebug('CloseConnection IGNORED because is ESConnection is CLOSED, reason %s', $reason);
            }

            return;
        }

        $this->logDebug('CloseConnection, reason %s, exception %s', $reason, $exception ? $exception->getMessage() : '<none>');

        $this->state = ConnectionState::Closed;

        $this->operations->cleanUp();
        $this->subscriptions->cleanUp();
        $this->closeTcpConnection();

        $this->logInfo('Closed. Reason: %s', $reason);

        if (null !== $exception) {
            $this->raiseErrorOccurred($exception);
        }

        $this->raiseClosed($reason);
    }

    /** @throws \Exception */
    private function establishTcpConnection(?DeferredFuture $deferred, NodeEndPoints $endPoints): void
    {
        $endPoint = $this->settings->useSslConnection()
            ? $endPoints->secureTcpEndPoint() ?? $endPoints->tcpEndPoint()
            : $endPoints->tcpEndPoint();

        if (null === $endPoint) {
            $this->closeConnection('No end point to node specified');

            $deferred?->complete();

            return;
        }

        $this->logDebug('EstablishTcpConnection to [%s]', (string) $endPoint);

        if ($this->state !== ConnectionState::Connecting
            || $this->connectingPhase !== ConnectingPhase::EndPointDiscovery
        ) {
            $deferred?->complete();

            return;
        }

        $this->connectingPhase = ConnectingPhase::ConnectionEstablishing;

        $this->connection = new TcpPackageConnection(
            $this->settings->log(),
            $endPoint,
            Guid::generateAsHex(),
            $this->settings->useSslConnection(),
            $this->settings->targetHost(),
            $this->settings->validateServer(),
            $this->settings->clientConnectionTimeout(),
            function (TcpPackageConnection $connection, TcpPackage $package): void {
                $this->enqueueMessage(new HandleTcpPackageMessage($connection, $package));
            },
            function (TcpPackageConnection $connection, Throwable $exception): void {
                $this->enqueueMessage(new TcpConnectionErrorMessage($connection, $exception));
            },
            function (TcpPackageConnection $connection): void {
                $this->enqueueMessage(new TcpConnectionEstablishedMessage($connection));
            },
            function (TcpPackageConnection $connection, Throwable $exception): void {
                $this->enqueueMessage(new TcpConnectionClosedMessage($connection, $exception));
            }
        );

        try {
            $this->connection->connect();
        } catch (Throwable $e) {
            $deferred?->error($e);

            return;
        }

        if (! $this->connection->isClosed()) {
            $this->connection->startReceiving();
        }

        if (! $deferred?->isComplete()) {
            $deferred?->complete();
        }
    }

    /** @throws \Exception */
    public function tcpConnectionError(TcpPackageConnection $tcpPackageConnection, Throwable $exception): void
    {
        if ($this->connection !== $tcpPackageConnection
            || $this->state === ConnectionState::Closed
        ) {
            return;
        }

        /** @psalm-suppress PossiblyNullReference */
        $this->logDebug('TcpConnectionError connId %s, exception %s', $this->connection->connectionId(), $exception->getMessage());
        $this->closeConnection('TCP connection error occurred', $exception);
    }

    /** @throws \Exception */
    private function closeTcpConnection(): void
    {
        if (null === $this->connection) {
            $this->logDebug('CloseTcpConnection IGNORED because connection === null');

            return;
        }

        $this->logDebug('CloseTcpConnection');
        $this->connection->close();

        $this->tcpConnectionClosed($this->connection);

        $this->connection = null;
    }

    /** @throws \Exception */
    private function tcpConnectionClosed(TcpPackageConnection $connection): void
    {
        if ($this->state === ConnectionState::Init) {
            throw new \Exception();
        }

        if ($this->connection !== $connection
            || $this->state === ConnectionState::Closed
        ) {
            $this->logDebug(
                'IGNORED (state: %s, internal conn.ID: {1:B}, conn.ID: %s): TCP connection to [%s] closed',
                $this->state->name,
                null === $this->connection ? Guid::empty() : $this->connection->connectionId(),
                $connection->connectionId(),
                (string) $connection->remoteEndPoint()
            );

            return;
        }

        $this->state = ConnectionState::Connecting;
        $this->connectingPhase = ConnectingPhase::Reconnecting;

        $this->logDebug(
            'TCP connection to [%s, %s] closed',
            (string) $connection->remoteEndPoint(),
            $connection->connectionId()
        );

        /** @psalm-suppress PossiblyNullReference */
        $this->subscriptions->purgeSubscribedAndDroppedSubscriptions($this->connection->connectionId());

        if (null === $this->reconnInfo) {
            $this->reconnInfo = new ReconnectionInfo(0, $this->stopWatch->elapsed());
        } else {
            $this->reconnInfo = new ReconnectionInfo($this->reconnInfo->reconnectionAttempt(), $this->stopWatch->elapsed());
        }

        if ($this->wasConnected) {
            $this->wasConnected = false;
            $this->raiseDisconnected($connection->remoteEndPoint());
        }
    }

    private function tcpConnectionEstablished(TcpPackageConnection $connection): void
    {
        /** @psalm-suppress PossiblyNullReference */
        if ($this->state !== ConnectionState::Connecting
            || $this->connection !== $connection
            || $this->connection->isClosed()
        ) {
            $this->logDebug(
                'IGNORED (state %s, internal conn.Id %s, conn.Id %s, conn.closed %s): TCP connection to [%s] established',
                $this->state->name,
                null === $this->connection ? Guid::empty() : $this->connection->connectionId(),
                $connection->connectionId(),
                $connection->isClosed() ? 'yes' : 'no',
                (string) $connection->remoteEndPoint()
            );

            return;
        }

        $this->logDebug(
            'TCP connection to [%s, %s] established',
            (string) $connection->remoteEndPoint(),
            $connection->connectionId()
        );
        $elapsed = $this->stopWatch->elapsed();

        $this->heartbeatInfo = new HeartbeatInfo($this->packageNumber, true, $elapsed);

        if ($this->settings->defaultUserCredentials() !== null) {
            $this->connectingPhase = ConnectingPhase::Authentication;

            $this->authInfo = new AuthInfo(Guid::generateAsHex(), $elapsed);

            $login = null;
            $pass = null;

            if ($this->settings->defaultUserCredentials()) {
                $login = $this->settings->defaultUserCredentials()->username();
                $pass = $this->settings->defaultUserCredentials()->password();
            }

            /** @psalm-suppress PossiblyNullReference */
            $this->connection->enqueueSend(new TcpPackage(
                TcpCommand::Authenticate,
                TcpFlags::Authenticated,
                $this->authInfo->correlationId(),
                '',
                $login,
                $pass
            ));
        } else {
            $this->goToIdentifyState();
        }
    }

    private function goToIdentifyState(): void
    {
        $this->connectingPhase = ConnectingPhase::Identification;
        $this->identityInfo = new IdentifyInfo(Guid::generateAsHex(), $this->stopWatch->elapsed());

        $message = new IdentifyClient();
        $message->setVersion(self::ClientVersion);
        $message->setConnectionName($this->esConnection->connectionName());

        /** @psalm-suppress PossiblyNullReference */
        $this->connection->enqueueSend(new TcpPackage(
            TcpCommand::IdentifyClient,
            TcpFlags::None,
            $this->identityInfo->correlationId(),
            $message->serializeToString()
        ));
    }

    private function goToConnectedState(): void
    {
        $this->state = ConnectionState::Connected;
        $this->connectingPhase = ConnectingPhase::Connected;
        $this->wasConnected = true;

        /** @psalm-suppress PossiblyNullReference */
        $this->raiseConnectedEvent($this->connection->remoteEndPoint());

        if ($this->stopWatch->elapsed() - $this->lastTimeoutsTimeStamp >= $this->settings->operationTimeoutCheckPeriod()) {
            $this->operations->checkTimeoutsAndRetry($this->connection);
            $this->subscriptions->checkTimeoutsAndRetry($this->connection);
            $this->lastTimeoutsTimeStamp = $this->stopWatch->elapsed();
        }
    }

    /** @throws Exception */
    private function timerTick(): void
    {
        $elapsed = $this->stopWatch->elapsed();

        switch ($this->state) {
            case ConnectionState::Init:
            case ConnectionState::Closed:
                break;
            case ConnectionState::Connecting:
                /** @psalm-suppress PossiblyNullReference */
                if ($this->connectingPhase === ConnectingPhase::Reconnecting
                    && $elapsed - $this->reconnInfo->timestamp() >= $this->settings->reconnectionDelay()
                ) {
                    $this->logDebug('TimerTick checking reconnection...');

                    $this->reconnInfo = new ReconnectionInfo($this->reconnInfo->reconnectionAttempt() + 1, $this->stopWatch->elapsed());

                    $maxReconnections = $this->settings->maxReconnections();

                    if ($maxReconnections >= 0 && $this->reconnInfo->reconnectionAttempt() > $maxReconnections) {
                        $this->closeConnection('Reconnection limit reached');
                    } else {
                        $this->raiseReconnecting();
                        $this->discoverEndPoint(null);
                    }
                }

                if ($this->connectingPhase === ConnectingPhase::Authentication
                    && $elapsed - $this->authInfo->timestamp() >= $this->settings->operationTimeout()
                ) {
                    $this->raiseAuthenticationFailed('Authentication timed out');
                    $this->goToIdentifyState();
                }

                if ($this->connectingPhase === ConnectingPhase::Identification
                    && $elapsed - $this->identityInfo->timestamp() >= $this->settings->operationTimeout()
                ) {
                    $this->logDebug('Timed out waiting for client to be identified');
                    $this->closeTcpConnection();
                }

                if ($this->connectingPhase->value > ConnectingPhase::ConnectionEstablishing->value) {
                    $this->manageHeartbeats();
                }

                break;
            case ConnectionState::Connected:
                /** @psalm-suppress PossiblyNullArgument */
                if ($elapsed - $this->lastTimeoutsTimeStamp >= $this->settings->operationTimeoutCheckPeriod()) {
                    $this->reconnInfo = new ReconnectionInfo(0, $elapsed);
                    $this->operations->checkTimeoutsAndRetry($this->connection);
                    $this->subscriptions->checkTimeoutsAndRetry($this->connection);
                    $this->lastTimeoutsTimeStamp = $elapsed;
                }

                $this->manageHeartbeats();

                break;
        }
    }

    /** @throws Exception */
    private function manageHeartbeats(): void
    {
        if (null === $this->connection) {
            throw new \Exception('Cannot manage heartbeats when no connection available');
        }

        $timeout = $this->heartbeatInfo->isIntervalStage() ? $this->settings->heartbeatInterval() : $this->settings->heartbeatTimeout();

        $elapsed = $this->stopWatch->elapsed();

        if ($elapsed - $this->heartbeatInfo->timestamp() < $timeout) {
            return;
        }

        $packageNumber = $this->packageNumber;

        if ($this->heartbeatInfo->lastPackageNumber() !== $packageNumber) {
            $this->heartbeatInfo = new HeartbeatInfo($packageNumber, true, $elapsed);

            return;
        }

        if ($this->heartbeatInfo->isIntervalStage()) {
            $this->connection->enqueueSend(new TcpPackage(
                TcpCommand::HeartbeatRequestCommand,
                TcpFlags::None,
                Guid::generateAsHex()
            ));

            $this->heartbeatInfo = new HeartbeatInfo($this->heartbeatInfo->lastPackageNumber(), false, $elapsed);
        } else {
            $msg = \sprintf(
                'EventStoreNodeConnection \'%s\': closing TCP connection [%s, %s] due to HEARTBEAT TIMEOUT at pkgNum %s',
                $this->esConnection->connectionName(),
                (string) $this->connection->remoteEndPoint(),
                $this->connection->connectionId(),
                $this->packageNumber
            );

            $this->settings->log()->info($msg);
            $this->closeTcpConnection();
        }
    }

    private function startOperation(ClientOperation $operation, int $maxRetries, float $timeout): void
    {
        switch ($this->state) {
            case ConnectionState::Init:
                $operation->fail(new InvalidOperationException(
                    \sprintf(
                        'EventStoreNodeConnection \'%s\' is not active',
                        $this->esConnection->connectionName()
                    )
                ));

                break;
            case ConnectionState::Connecting:
                $this->logDebug(
                    'StartOperation enqueue %s, %s, %s, %s',
                    $operation->name(),
                    (string) $operation,
                    (string) $maxRetries,
                    (string) $timeout
                );
                $this->operations->enqueueOperation(new OperationItem($operation, $maxRetries, $timeout));

                break;
            case ConnectionState::Connected:
                $this->logDebug(
                    'StartOperation schedule %s, %s, %s, %s',
                    $operation->name(),
                    (string) $operation,
                    (string) $maxRetries,
                    (string) $timeout
                );
                /** @psalm-suppress PossiblyNullArgument */
                $this->operations->scheduleOperation(new OperationItem($operation, $maxRetries, $timeout), $this->connection);

                break;
            case ConnectionState::Closed:
                $operation->fail(new ObjectDisposed(\sprintf(
                    'EventStoreNodeConnection \'%s\' is closed',
                    $this->esConnection->connectionName()
                )));

                break;
        }
    }

    private function startSubscription(StartSubscriptionMessage $message): void
    {
        switch ($this->state) {
            case ConnectionState::Init:
                $message->deferred()->error(new InvalidOperationException(\sprintf(
                    'EventStoreNodeConnection \'%s\' is not active',
                    $this->esConnection->connectionName()
                )));

                break;
            case ConnectionState::Connecting:
            case ConnectionState::Connected:
                $operation = new VolatileSubscriptionOperation(
                    $this->settings->log(),
                    $message->deferred(),
                    $message->streamId(),
                    $message->resolveTo(),
                    $message->userCredentials(),
                    function (EventStoreSubscription $subscription, ResolvedEvent $resolvedEvent) use ($message): void {
                        ($message->eventAppeared())($subscription, $resolvedEvent);
                    },
                    function (EventStoreSubscription $subscription, SubscriptionDropReason $reason, ?Throwable $exception = null) use ($message): void {
                        $subscriptionDroppedHandler = $message->subscriptionDropped();

                        if (null !== $subscriptionDroppedHandler) {
                            $subscriptionDroppedHandler($subscription, $reason, $exception);
                        }
                    },
                    $this->settings->verboseLogging(),
                    fn (): ?TcpPackageConnection => $this->connection
                );

                $this->logDebug(
                    'StartSubscription %s %s, %s, MaxRetries: %d, Timeout: %d',
                    $this->state === ConnectionState::Connected ? 'fire' : 'enqueue',
                    $operation->name(),
                    (string) $operation,
                    (string) $message->maxRetries(),
                    (string) $message->timeout()
                );

                $subscription = new SubscriptionItem($operation, $message->maxRetries(), $message->timeout());

                if ($this->state === ConnectionState::Connecting) {
                    $this->subscriptions->enqueueSubscription($subscription);
                } else {
                    /** @psalm-suppress PossiblyNullArgument */
                    $this->subscriptions->startSubscription($subscription, $this->connection);
                }

                break;
            case ConnectionState::Closed:
                $message->deferred()->error(new ObjectDisposed(\sprintf(
                    'EventStoreNodeConnection \'%s\' is closed',
                    $this->esConnection->connectionName()
                )));

                break;
        }
    }

    private function startPersistentSubscription(StartPersistentSubscriptionMessage $message): void
    {
        switch ($this->state) {
            case ConnectionState::Init:
                $message->deferred()->error(new InvalidOperationException(\sprintf(
                    'EventStoreNodeConnection \'%s\' is not active',
                    $this->esConnection->connectionName()
                )));

                break;
            case ConnectionState::Connecting:
            case ConnectionState::Connected:
                $operation = new ConnectToPersistentSubscriptionOperation(
                    $this->settings->log(),
                    $message->deferred(),
                    $message->subscriptionId(),
                    $message->bufferSize(),
                    $message->streamId(),
                    $message->userCredentials(),
                    $message->eventAppeared(),
                    $message->subscriptionDropped(),
                    $this->settings->verboseLogging(),
                    fn (): ?TcpPackageConnection => $this->connection
                );

                $this->logDebug(
                    'StartSubscription %s %s, %s, MaxRetries: %d, Timeout: %d',
                    $this->state === ConnectionState::Connected ? 'fire' : 'enqueue',
                    $operation->name(),
                    (string) $operation,
                    (string) $message->maxRetries(),
                    (string) $message->timeout()
                );

                $subscription = new SubscriptionItem($operation, $message->maxRetries(), $message->timeout());

                if ($this->state === ConnectionState::Connecting) {
                    $this->subscriptions->enqueueSubscription($subscription);
                } else {
                    /** @psalm-suppress PossiblyNullArgument */
                    $this->subscriptions->startSubscription($subscription, $this->connection);
                }

                break;
            case ConnectionState::Closed:
                $message->deferred()->error(new ObjectDisposed(\sprintf(
                    'EventStoreNodeConnection \'%s\' is closed',
                    $this->esConnection->connectionName()
                )));

                break;
        }
    }

    /** @throws Exception */
    private function handleTcpPackage(TcpPackageConnection $connection, TcpPackage $package): void
    {
        if ($this->connection !== $connection
            || $this->state === ConnectionState::Closed
            || $this->state === ConnectionState::Init
        ) {
            $this->logDebug(
                'IGNORED: HandleTcpPackage connId %s, package %s, %s',
                $connection->connectionId(),
                $package->command()->name,
                $package->correlationId()
            );

            return;
        }

        /** @psalm-suppress PossiblyNullReference */
        $this->logDebug(
            'HandleTcpPackage connId %s, package %s, %s',
            $this->connection->connectionId(),
            $package->command()->name,
            $package->correlationId()
        );

        ++$this->packageNumber;

        if ($package->command() === TcpCommand::HeartbeatResponseCommand) {
            return;
        }

        if ($package->command() === TcpCommand::HeartbeatRequestCommand) {
            $this->connection->enqueueSend(new TcpPackage(
                TcpCommand::HeartbeatResponseCommand,
                TcpFlags::None,
                $package->correlationId()
            ));

            return;
        }

        if ($package->command() === TcpCommand::Authenticated
            || $package->command() === TcpCommand::NotAuthenticatedException
        ) {
            if ($this->state === ConnectionState::Connecting
                && $this->connectingPhase === ConnectingPhase::Authentication
                && $this->authInfo->correlationId() === $package->correlationId()
            ) {
                if ($package->command() === TcpCommand::NotAuthenticatedException) {
                    $this->raiseAuthenticationFailed('Not authenticated');
                }

                $this->goToIdentifyState();

                return;
            }
        }

        if ($package->command() === TcpCommand::ClientIdentified
            && $this->state === ConnectionState::Connecting
            && $this->identityInfo->correlationId() === $package->correlationId()
        ) {
            $this->goToConnectedState();

            return;
        }

        if ($package->command() === TcpCommand::BadRequest
            && $package->correlationId() === ''
        ) {
            $exception = new EventStoreConnectionException('Bad request received from server');
            $this->closeConnection('Connection-wide BadRequest received. Too dangerous to continue', $exception);

            return;
        }

        if ($operation = $this->operations->getActiveOperation($package->correlationId())) {
            $result = $operation->operation()->inspectPackage($package);

            $this->logDebug(
                'HandleTcpPackage OPERATION DECISION %s (%s), %s',
                $result->decision()->name,
                $result->description(),
                (string) $operation
            );

            switch ($result->decision()) {
                case InspectionDecision::DoNothing:
                    break;
                case InspectionDecision::EndOperation:
                    $this->operations->removeOperation($operation);

                    break;
                case InspectionDecision::Retry:
                    $this->operations->scheduleOperationRetry($operation);

                    break;
                case InspectionDecision::Reconnect:
                    $this->reconnectTo(new NodeEndPoints($result->tcpEndPoint(), $result->secureTcpEndPoint()));
                    $this->operations->scheduleOperationRetry($operation);

                    break;
            }

            if ($this->state === ConnectionState::Connected) {
                $this->operations->tryScheduleWaitingOperations($connection);
            }
        } elseif ($subscription = $this->subscriptions->getActiveSubscription($package->correlationId())) {
            $result = $subscription->operation()->inspectPackage($package);

            $this->logDebug(
                'HandleTcpPackage %s SUBSCRIPTION DECISION %s (%s), %s',
                $package->correlationId(),
                $result->decision()->name,
                $result->description(),
                (string) $operation
            );

            switch ($result->decision()) {
                case InspectionDecision::DoNothing:
                    break;
                case InspectionDecision::EndOperation:
                    $this->subscriptions->removeSubscription($subscription);

                    break;
                case InspectionDecision::Retry:
                    $this->subscriptions->scheduleSubscriptionRetry($subscription);

                    break;
                case InspectionDecision::Reconnect:
                    $this->reconnectTo(new NodeEndPoints($result->tcpEndPoint(), $result->secureTcpEndPoint()));
                    $this->subscriptions->scheduleSubscriptionRetry($subscription);

                    break;
                case InspectionDecision::Subscribed:
                    $subscription->setIsSubscribed(true);

                    break;
            }
        } else {
            $this->logDebug(
                'HandleTcpPackage UNMAPPED PACKAGE with CorrelationId %s, Command: %s',
                $package->correlationId(),
                $package->command()->name
            );
        }
    }

    /** @throws Exception */
    private function reconnectTo(NodeEndPoints $endPoints): void
    {
        $endPoint = $this->settings->useSslConnection()
            ? $endPoints->secureTcpEndPoint() ?? $endPoints->tcpEndPoint()
            : $endPoints->tcpEndPoint();

        if (null === $endPoint) {
            $this->closeConnection('No end point is specified while trying to reconnect');

            return;
        }

        /** @psalm-suppress PossiblyNullReference */
        if ($this->state !== ConnectionState::Connected
            || $this->connection->remoteEndPoint()->equals($endPoint)
        ) {
            return;
        }

        /** @psalm-suppress PossiblyNullReference */
        $msg = \sprintf(
            'EventStoreNodeConnection \'%s\': going to reconnect to [%s]. Current end point: [%s]',
            $this->esConnection->connectionName(),
            (string) $endPoint,
            (string) $this->connection->remoteEndPoint()
        );

        if ($this->settings->verboseLogging()) {
            $this->settings->log()->info($msg);
        }

        $this->closeTcpConnection();

        $this->state = ConnectionState::Connecting;
        $this->connectingPhase = ConnectingPhase::EndPointDiscovery;

        $this->establishTcpConnection(null, $endPoints);
    }

    private function logDebug(string $message, string ...$parameters): void
    {
        if ($this->settings->verboseLogging()) {
            $message = empty($parameters)
                ? $message
                : \sprintf($message, ...$parameters);

            $this->settings->log()->debug(\sprintf(
                'EventStoreNodeConnection \'%s\': %s',
                $this->esConnection->connectionName(),
                $message
            ));
        }
    }

    private function logInfo(string $message, string ...$parameters): void
    {
        if ($this->settings->verboseLogging()) {
            $message = empty($parameters)
                ? $message
                : \sprintf($message, ...$parameters);

            $this->settings->log()->info(\sprintf(
                'EventStoreNodeConnection \'%s\': %s',
                $this->esConnection->connectionName(),
                $message
            ));
        }
    }

    private function raiseConnectedEvent(EndPoint $remoteEndPoint): void
    {
        $this->eventHandler->connected(new ClientConnectionEventArgs($this->esConnection, $remoteEndPoint));
    }

    private function raiseDisconnected(EndPoint $remoteEndPoint): void
    {
        $this->eventHandler->disconnected(new ClientConnectionEventArgs($this->esConnection, $remoteEndPoint));
    }

    private function raiseErrorOccurred(Throwable $e): void
    {
        $this->eventHandler->errorOccurred(new ClientErrorEventArgs($this->esConnection, $e));
    }

    private function raiseClosed(string $reason): void
    {
        $this->eventHandler->closed(new ClientClosedEventArgs($this->esConnection, $reason));
    }

    private function raiseReconnecting(): void
    {
        $this->eventHandler->reconnecting(new ClientReconnectingEventArgs($this->esConnection));
    }

    private function raiseAuthenticationFailed(string $reason): void
    {
        $this->eventHandler->authenticationFailed(new ClientAuthenticationFailedEventArgs($this->esConnection, $reason));
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

    public function detach(ListenerHandler $handler): void
    {
        $this->eventHandler->detach($handler);
    }
}
