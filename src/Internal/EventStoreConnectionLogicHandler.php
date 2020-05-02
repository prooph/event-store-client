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
use Amp\Loop;
use Amp\Promise;
use Closure;
use Exception;
use Generator;
use Prooph\EventStore\Async\ClientAuthenticationFailedEventArgs;
use Prooph\EventStore\Async\ClientClosedEventArgs;
use Prooph\EventStore\Async\ClientConnectionEventArgs;
use Prooph\EventStore\Async\ClientErrorEventArgs;
use Prooph\EventStore\Async\ClientReconnectingEventArgs;
use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\Async\Internal\EventHandler;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\EventStoreSubscription;
use Prooph\EventStore\Exception\CannotEstablishConnection;
use Prooph\EventStore\Exception\EventStoreConnectionException;
use Prooph\EventStore\Exception\InvalidOperationException;
use Prooph\EventStore\Exception\ObjectDisposed;
use Prooph\EventStore\Internal\Consts;
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
use Throwable;

/** @internal */
class EventStoreConnectionLogicHandler
{
    private const CLIENT_VERSION = 1;

    private EventStoreConnection $esConnection;
    private ?TcpPackageConnection $connection = null;
    private ConnectionSettings $settings;
    private ConnectionState $state;
    private ConnectingPhase $connectingPhase;
    private EndPointDiscoverer $endPointDiscoverer;
    private MessageHandler $handler;
    private OperationsManager $operations;
    private SubscriptionsManager $subscriptions;
    private EventHandler $eventHandler;
    private StopWatch $stopWatch;
    private string $timerTickWatcherId = '';
    private ?ReconnectionInfo $reconnInfo = null;
    private HeartbeatInfo $heartbeatInfo;
    private AuthInfo $authInfo;
    private IdentifyInfo $identityInfo;
    private bool $wasConnected = false;
    private int $packageNumber = 0;
    private int $lastTimeoutsTimeStamp;

    public function __construct(EventStoreConnection $connection, ConnectionSettings $settings)
    {
        $this->esConnection = $connection;
        $this->settings = $settings;
        $this->state = ConnectionState::init();
        $this->connectingPhase = ConnectingPhase::invalid();
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
                $this->establishTcpConnection($message->nodeEndPoints());
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
                $this->tcpConnectionClosed($message->tcpPackageConnection());
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

    private function startConnection(Deferred $deferred, EndPointDiscoverer $endPointDiscoverer): void
    {
        $this->logDebug('startConnection');

        switch ($this->state->value()) {
            case ConnectionState::INIT:
                $this->timerTickWatcherId = Loop::repeat(Consts::TIMER_PERIOD, function (): void {
                    $this->timerTick();
                });
                $this->endPointDiscoverer = $endPointDiscoverer;
                $this->state = ConnectionState::connecting();
                $this->connectingPhase = ConnectingPhase::reconnecting();
                $this->discoverEndPoint($deferred);
                break;
            case ConnectionState::CONNECTING:
            case ConnectionState::CONNECTED:
                $deferred->fail(new InvalidOperationException(\sprintf(
                    'EventStoreNodeConnection \'%s\' is already active',
                    $this->esConnection->connectionName()
                )));
                break;
            case ConnectionState::CLOSED:
                $deferred->fail(new ObjectDisposed(\sprintf(
                    'EventStoreNodeConnection \'%s\' is closed',
                    $this->esConnection->connectionName()
                )));
                break;
        }
    }

    private function discoverEndPoint(?Deferred $deferred): void
    {
        $this->logDebug('discoverEndPoint');

        if (! $this->state->equals(ConnectionState::connecting())) {
            return;
        }

        if (! $this->connectingPhase->equals(ConnectingPhase::reconnecting())) {
            return;
        }

        $this->connectingPhase = ConnectingPhase::endPointDiscovery();

        $promise = $this->endPointDiscoverer->discoverAsync(
            null !== $this->connection
                ? $this->connection->remoteEndPoint()
                : null
        );

        $promise->onResolve(function (?Throwable $e, ?NodeEndPoints $endpoints = null) use ($deferred): void {
            if ($e) {
                $this->enqueueMessage(new CloseConnectionMessage(
                    'Failed to resolve TCP end point to which to connect',
                    $e
                ));

                if ($deferred) {
                    $deferred->fail(new CannotEstablishConnection('Cannot resolve target end point'));
                }

                return;
            }

            $this->enqueueMessage(new EstablishTcpConnectionMessage($endpoints));

            if ($deferred) {
                $deferred->resolve(null);
            }
        });
    }

    /** @throws Exception */
    private function closeConnection(string $reason, ?Throwable $exception = null): void
    {
        if ($this->state->equals(ConnectionState::closed())) {
            if ($exception) {
                $this->logDebug('CloseConnection IGNORED because is ESConnection is CLOSED, reason %s, exception %s', $reason, $exception->getMessage());
            } else {
                $this->logDebug('CloseConnection IGNORED because is ESConnection is CLOSED, reason %s', $reason);
            }

            return;
        }

        $this->logDebug('CloseConnection, reason %s, exception %s', $reason, $exception ? $exception->getMessage() : '<none>');

        $this->state = ConnectionState::closed();

        if ($this->timerTickWatcherId) {
            Loop::cancel($this->timerTickWatcherId);
        }

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
    private function establishTcpConnection(NodeEndPoints $endPoints): void
    {
        $endPoint = $this->settings->useSslConnection()
            ? $endPoints->secureTcpEndPoint() ?? $endPoints->tcpEndPoint()
            : $endPoints->tcpEndPoint();

        if (null === $endPoint) {
            $this->closeConnection('No end point to node specified');

            return;
        }

        $this->logDebug('EstablishTcpConnection to [%s]', (string) $endPoint);

        if (! $this->state->equals(ConnectionState::connecting())
            || ! $this->connectingPhase->equals(ConnectingPhase::endPointDiscovery())
        ) {
            return;
        }

        $this->connectingPhase = ConnectingPhase::connectionEstablishing();

        Loop::defer(function () use ($endPoint): Generator {
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

            yield $this->connection->connectAsync();

            if (null !== $this->connection && ! $this->connection->isClosed()) {
                $this->connection->startReceiving();
            }
        });
    }

    /** @throws \Exception */
    public function tcpConnectionError(TcpPackageConnection $tcpPackageConnection, Throwable $exception): void
    {
        if ($this->connection !== $tcpPackageConnection
            || $this->state->equals(ConnectionState::closed())
        ) {
            return;
        }

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
        if ($this->state->equals(ConnectionState::init())) {
            throw new \Exception();
        }

        if ($this->connection !== $connection
            || $this->state->equals(ConnectionState::closed())
        ) {
            $this->logDebug('IGNORED (state: %s, internal conn.ID: {1:B}, conn.ID: %s): TCP connection to [%s] closed',
                $this->state,
                null === $this->connection ? Guid::empty() : $this->connection->connectionId(),
                $connection->connectionId(),
                $connection->remoteEndPoint()
            );

            return;
        }

        $this->state = ConnectionState::connecting();
        $this->connectingPhase = ConnectingPhase::reconnecting();

        $this->logDebug(
            'TCP connection to [%s, %s] closed',
            (string) $connection->remoteEndPoint(),
            $connection->connectionId()
        );

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
        if (! $this->state->equals(ConnectionState::connecting())
            || $this->connection !== $connection
            || $this->connection->isClosed()
        ) {
            $this->logDebug('IGNORED (state %s, internal conn.Id %s, conn.Id %s, conn.closed %s): TCP connection to [%s] established',
                $this->state,
                null === $this->connection ? Guid::empty() : $this->connection->connectionId(),
                $connection->connectionId(),
                $connection->isClosed() ? 'yes' : 'no',
                $connection->remoteEndPoint()
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
            $this->connectingPhase = ConnectingPhase::authentication();

            $this->authInfo = new AuthInfo(Guid::generateAsHex(), $elapsed);

            $login = null;
            $pass = null;

            if ($this->settings->defaultUserCredentials()) {
                $login = $this->settings->defaultUserCredentials()->username();
                $pass = $this->settings->defaultUserCredentials()->password();
            }

            $this->connection->enqueueSend(new TcpPackage(
                TcpCommand::authenticate(),
                TcpFlags::authenticated(),
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
        $this->connectingPhase = ConnectingPhase::identification();
        $this->identityInfo = new IdentifyInfo(Guid::generateAsHex(), $this->stopWatch->elapsed());

        $message = new IdentifyClient();
        $message->setVersion(self::CLIENT_VERSION);
        $message->setConnectionName($this->esConnection->connectionName());

        $this->connection->enqueueSend(new TcpPackage(
            TcpCommand::identifyClient(),
            TcpFlags::none(),
            $this->identityInfo->correlationId(),
            $message->serializeToString()
        ));
    }

    private function goToConnectedState(): void
    {
        $this->state = ConnectionState::connected();
        $this->connectingPhase = ConnectingPhase::connected();
        $this->wasConnected = true;

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

        switch ($this->state->value()) {
            case ConnectionState::INIT:
                break;
            case ConnectionState::CONNECTING:
                if ($this->connectingPhase->equals(ConnectingPhase::reconnecting())
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

                if ($this->connectingPhase->equals(ConnectingPhase::authentication())
                    && $elapsed - $this->authInfo->timestamp() >= $this->settings->operationTimeout()
                ) {
                    $this->raiseAuthenticationFailed('Authentication timed out');
                    $this->goToIdentifyState();
                }

                if ($this->connectingPhase->equals(ConnectingPhase::identification())
                    && $elapsed - $this->identityInfo->timestamp() >= $this->settings->operationTimeout()
                ) {
                    $this->logDebug('Timed out waiting for client to be identified');
                    $this->closeTcpConnection();
                }

                if ($this->connectingPhase->value() > ConnectingPhase::CONNECTION_ESTABLISHING) {
                    $this->manageHeartbeats();
                }

                break;
            case ConnectionState::CONNECTED:
                if ($elapsed - $this->lastTimeoutsTimeStamp >= $this->settings->operationTimeoutCheckPeriod()) {
                    $this->reconnInfo = new ReconnectionInfo(0, $elapsed);
                    $this->operations->checkTimeoutsAndRetry($this->connection);
                    $this->subscriptions->checkTimeoutsAndRetry($this->connection);
                    $this->lastTimeoutsTimeStamp = $elapsed;
                }

                $this->manageHeartbeats();

                break;
            case ConnectionState::CLOSED:
                break;
        }
    }

    /** @throws Exception */
    private function manageHeartbeats(): void
    {
        if (null === $this->connection) {
            throw new \Exception();
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
                TcpCommand::heartbeatRequestCommand(),
                TcpFlags::none(),
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

    private function startOperation(ClientOperation $operation, int $maxRetries, int $timeout): void
    {
        switch ($this->state->value()) {
            case ConnectionState::INIT:
                $operation->fail(new InvalidOperationException(
                    \sprintf(
                        'EventStoreNodeConnection \'%s\' is not active',
                        $this->esConnection->connectionName()
                    )
                ));
                break;
            case ConnectionState::CONNECTING:
                $this->logDebug(
                    'StartOperation enqueue %s, %s, %s',
                    $operation->name(),
                    //$operation,
                    $maxRetries,
                    $timeout
                );
                $this->operations->enqueueOperation(new OperationItem($operation, $maxRetries, $timeout));
                break;
            case ConnectionState::CONNECTED:
                $this->logDebug(
                    'StartOperation schedule %s, %s, %s, %s',
                    $operation->name(),
                    (string) $operation,
                    $maxRetries,
                    $timeout
                );
                $this->operations->scheduleOperation(new OperationItem($operation, $maxRetries, $timeout), $this->connection);
                break;
            case ConnectionState::CLOSED:
                $operation->fail(new ObjectDisposed(\sprintf(
                    'EventStoreNodeConnection \'%s\' is closed',
                    $this->esConnection->connectionName()
                )));
                break;
        }
    }

    private function startSubscription(StartSubscriptionMessage $message): void
    {
        switch ($this->state->value()) {
            case ConnectionState::INIT:
                $message->deferred()->fail(new InvalidOperationException(\sprintf(
                    'EventStoreNodeConnection \'%s\' is not active',
                    $this->esConnection->connectionName()
                )));
                break;
            case ConnectionState::CONNECTING:
            case ConnectionState::CONNECTED:
                $operation = new VolatileSubscriptionOperation(
                    $this->settings->log(),
                    $message->deferred(),
                    $message->streamId(),
                    $message->resolveTo(),
                    $message->userCredentials(),
                    fn (EventStoreSubscription $subscription, ResolvedEvent $resolvedEvent): Promise => ($message->eventAppeared())($subscription, $resolvedEvent),
                    function (EventStoreSubscription $subscription, SubscriptionDropReason $reason, ?Throwable $exception = null) use ($message): void {
                        ($message->subscriptionDropped())($subscription, $reason, $exception);
                    },
                    $this->settings->verboseLogging(),
                    fn (): ?TcpPackageConnection => $this->connection
                );

                $this->logDebug(
                    'StartSubscription %s %s, %s, MaxRetries: %d, Timeout: %d',
                    $this->state->equals(ConnectionState::connected()) ? 'fire' : 'enqueue',
                    $operation->name(),
                    (string) $operation,
                    $message->maxRetries(),
                    $message->timeout()
                );

                $subscription = new SubscriptionItem($operation, $message->maxRetries(), $message->timeout());

                if ($this->state->equals(ConnectionState::connecting())) {
                    $this->subscriptions->enqueueSubscription($subscription);
                } else {
                    $this->subscriptions->startSubscription($subscription, $this->connection);
                }

                break;
            case ConnectionState::CLOSED:
                $message->deferred()->fail(new ObjectDisposed(\sprintf(
                    'EventStoreNodeConnection \'%s\' is closed',
                    $this->esConnection->connectionName()
                )));
                break;
        }
    }

    private function startPersistentSubscription(StartPersistentSubscriptionMessage $message): void
    {
        switch ($this->state->value()) {
            case ConnectionState::INIT:
                $message->deferred()->fail(new InvalidOperationException(\sprintf(
                    'EventStoreNodeConnection \'%s\' is not active',
                    $this->esConnection->connectionName()
                )));
                break;
            case ConnectionState::CONNECTING:
            case ConnectionState::CONNECTED:
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
                    $this->state->equals(ConnectionState::connected()) ? 'fire' : 'enqueue',
                    $operation->name(),
                    (string) $operation,
                    $message->maxRetries(),
                    $message->timeout()
                );

                $subscription = new SubscriptionItem($operation, $message->maxRetries(), $message->timeout());

                if ($this->state->equals(ConnectionState::connecting())) {
                    $this->subscriptions->enqueueSubscription($subscription);
                } else {
                    $this->subscriptions->startSubscription($subscription, $this->connection);
                }

                break;
            case ConnectionState::CLOSED:
                $message->deferred()->fail(new ObjectDisposed(\sprintf(
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
            || $this->state->equals(ConnectionState::closed())
            || $this->state->equals(ConnectionState::init())
        ) {
            $this->logDebug(
                'IGNORED: HandleTcpPackage connId %s, package %s, %s',
                $connection->connectionId(),
                (string) $package->command(),
                $package->correlationId()
            );

            return;
        }

        $this->logDebug(
            'HandleTcpPackage connId %s, package %s, %s',
            $this->connection->connectionId(),
            (string) $package->command(),
            $package->correlationId()
        );

        ++$this->packageNumber;

        if ($package->command()->equals(TcpCommand::heartbeatResponseCommand())) {
            return;
        }

        if ($package->command()->equals(TcpCommand::heartbeatRequestCommand())) {
            $this->connection->enqueueSend(new TcpPackage(
                TcpCommand::heartbeatResponseCommand(),
                TcpFlags::none(),
                $package->correlationId()
            ));

            return;
        }

        if ($package->command()->equals(TcpCommand::authenticated())
            || $package->command()->equals(TcpCommand::notAuthenticatedException())
        ) {
            if ($this->state->equals(ConnectionState::connecting())
                && $this->connectingPhase->equals(ConnectingPhase::authentication())
                && $this->authInfo->correlationId() === $package->correlationId()
            ) {
                if ($package->command()->equals(TcpCommand::notAuthenticatedException())) {
                    $this->raiseAuthenticationFailed('Not authenticated');
                }

                $this->goToIdentifyState();

                return;
            }
        }

        if ($package->command()->equals(TcpCommand::clientIdentified())
            && $this->state->equals(ConnectionState::connecting())
            && $this->identityInfo->correlationId() === $package->correlationId()
        ) {
            $this->goToConnectedState();

            return;
        }

        if ($package->command()->equals(TcpCommand::badRequest())
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
                (string) $result->decision(),
                $result->description(),
                (string) $operation
            );

            switch ($result->decision()->value()) {
                case InspectionDecision::DO_NOTHING:
                    break;
                case InspectionDecision::END_OPERATION:
                    $this->operations->removeOperation($operation);
                    break;
                case InspectionDecision::RETRY:
                    $this->operations->scheduleOperationRetry($operation);
                    break;
                case InspectionDecision::RECONNECT:
                    $this->reconnectTo(new NodeEndPoints($result->tcpEndPoint(), $result->secureTcpEndPoint()));
                    $this->operations->scheduleOperationRetry($operation);
                    break;
            }

            if ($this->state->equals(ConnectionState::connected())) {
                $this->operations->tryScheduleWaitingOperations($connection);
            }
        } elseif ($subscription = $this->subscriptions->getActiveSubscription($package->correlationId())) {
            $result = $subscription->operation()->inspectPackage($package);

            $this->logDebug(
                'HandleTcpPackage %s SUBSCRIPTION DECISION %s (%s), %s',
                $package->correlationId(),
                (string) $result->decision(),
                $result->description(),
                (string) $operation
            );

            switch ($result->decision()->value()) {
                case InspectionDecision::DO_NOTHING:
                    break;
                case InspectionDecision::END_OPERATION:
                    $this->subscriptions->removeSubscription($subscription);
                    break;
                case InspectionDecision::RETRY:
                    $this->subscriptions->scheduleSubscriptionRetry($subscription);
                    break;
                case InspectionDecision::RECONNECT:
                    $this->reconnectTo(new NodeEndPoints($result->tcpEndPoint(), $result->secureTcpEndPoint()));
                    $this->subscriptions->scheduleSubscriptionRetry($subscription);
                    break;
                case InspectionDecision::SUBSCRIBED:
                    $subscription->setIsSubscribed(true);
                    break;
            }
        } else {
            $this->logDebug(
                'HandleTcpPackage UNMAPPED PACKAGE with CorrelationId %s, Command: %s',
                $package->correlationId(),
                (string) $package->command()
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

        if (! $this->state->equals(ConnectionState::connected())
            || $this->connection->remoteEndPoint()->equals($endPoint)
        ) {
            return;
        }

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

        $this->state = ConnectionState::connecting();
        $this->connectingPhase = ConnectingPhase::endPointDiscovery();

        $this->establishTcpConnection($endPoints);
    }

    private function logDebug(string $message, ...$parameters): void
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

    private function logInfo(string $message, ...$parameters): void
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
