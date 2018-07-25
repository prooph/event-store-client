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
use Amp\Loop;
use Generator;
use Prooph\EventStoreClient\ClientAuthenticationFailedEventArgs;
use Prooph\EventStoreClient\ClientClosedEventArgs;
use Prooph\EventStoreClient\ClientConnectionEventArgs;
use Prooph\EventStoreClient\ClientErrorEventArgs;
use Prooph\EventStoreClient\ClientOperations\ClientOperation;
use Prooph\EventStoreClient\ClientOperations\ConnectToPersistentSubscriptionOperation;
use Prooph\EventStoreClient\ClientOperations\VolatileSubscriptionOperation;
use Prooph\EventStoreClient\ClientReconnectingEventArgs;
use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStoreClient\Exception\CannotEstablishConnectionException;
use Prooph\EventStoreClient\Exception\ConnectionClosedException;
use Prooph\EventStoreClient\Exception\EventStoreConnectionException;
use Prooph\EventStoreClient\Exception\InvalidOperationException;
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
use Prooph\EventStoreClient\IpEndPoint;
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
    private const ClientVersion = 1;

    /** @var EventStoreAsyncNodeConnection */
    private $esConnection;
    /** @var TcpPackageConnection */
    private $connection;
    /** @var ConnectionSettings */
    private $settings;
    /** @var ConnectionState */
    private $state;
    /** @var ConnectingPhase */
    private $connectingPhase;
    /** @var EndPointDiscoverer */
    private $endPointDiscoverer;
    /** @var MessageHandler */
    private $handler;
    /** @var OperationsManager */
    private $operations;
    /** @var SubscriptionsManager */
    private $subscriptions;
    /** @var EventHandler */
    private $eventHandler;
    /** @var StopWatch */
    private $stopWatch;
    /** @var string */
    private $timerTickWatcherId;

    /** @var ReconnectionInfo */
    private $reconnInfo;
    /** @var HeartbeatInfo */
    private $heartbeatInfo;
    /** @var AuthInfo */
    private $authInfo;
    /** @var IdentifyInfo */
    private $identityInfo;
    /** @var bool */
    private $wasConnected = false;
    /** @var int */
    private $packageNumber = 0;
    /** @var int */
    private $lastTimeoutsTimeStamp = 0;

    public function __construct(EventStoreAsyncNodeConnection $connection, ConnectionSettings $settings)
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
        return $this->operations ? $this->operations->totalOperationCount() : 0;
    }

    public function enqueueMessage(Message $message): void
    {
        $this->logDebug(\sprintf('enqueing message %s', $message));

        $this->handler->handle($message);
    }

    private function startConnection(Deferred $deferred, EndPointDiscoverer $endPointDiscoverer): void
    {
        $this->logDebug('startConnection');

        switch ($this->state->value()) {
            case ConnectionState::Init:
                $this->timerTickWatcherId = Loop::repeat(Consts::TimerPeriod, function (): void {
                    $this->timerTick();
                });
                $this->endPointDiscoverer = $endPointDiscoverer;
                $this->state = ConnectionState::connecting();
                $this->connectingPhase = ConnectingPhase::reconnecting();
                $this->discoverEndPoint($deferred);
                break;
            case ConnectionState::Connecting:
            case ConnectionState::Connected:
                $deferred->fail(new InvalidOperationException(\sprintf(
                    'EventStoreNodeConnection \'%s\' is already active',
                    $this->esConnection->connectionName()
                )));
                break;
            case ConnectionState::Closed:
                $deferred->fail(ConnectionClosedException::withName($this->esConnection->connectionName()));
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

        $promise->onResolve(function (?\Throwable $e, NodeEndPoints $endpoints = null) use ($deferred): void {
            if ($e) {
                $this->enqueueMessage(new CloseConnectionMessage(
                    'Failed to resolve TCP end point to which to connect',
                    $e
                ));

                if ($deferred) {
                    $deferred->fail(new CannotEstablishConnectionException('Cannot resolve target end point'));
                }

                return;
            }

            $this->enqueueMessage(new EstablishTcpConnectionMessage($endpoints));

            if ($deferred) {
                $deferred->resolve(null);
            }
        });

        Loop::defer(function () use ($promise): Generator {
            yield $promise;
        });
    }

    /** @throws \Exception */
    private function closeConnection(string $reason, Throwable $exception = null): void
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
        $this->closeTcpConnection($reason);

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

        $this->logDebug('EstablishTcpConnection to [%s]', $endPoint);

        if (! $this->state->equals(ConnectionState::connecting())
            || ! $this->connectingPhase->equals(ConnectingPhase::endPointDiscovery())
        ) {
            return;
        }

        $this->connectingPhase = ConnectingPhase::connectionEstablishing();

        $this->connection = new TcpPackageConnection(
            $this->settings->log(),
            $endPoint,
            UuidGenerator::generate(),
            $this->settings->useSslConnection(),
            $this->settings->targetHost(),
            $this->settings->validateServer(),
            $this->settings->clientConnectionTimeout(),
            function (TcpPackageConnection $connection, TcpPackage $package): void {
                $this->enqueueMessage(new HandleTcpPackageMessage($connection, $package));
            },
            function (TcpPackageConnection $connection, \Throwable $exception): void {
                $this->enqueueMessage(new TcpConnectionErrorMessage($connection, $exception));
            },
            function (TcpPackageConnection $connection): void {
                $this->enqueueMessage(new TcpConnectionEstablishedMessage($connection));
            },
            function (TcpPackageConnection $connection, \Throwable $exception): void {
                $this->enqueueMessage(new TcpConnectionClosedMessage($connection, $exception));
            }
        );

        Loop::defer(function (): Generator {
            yield $this->connection->connectAsync();

            if (! $this->connection->isClosed()) {
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
    private function closeTcpConnection(string $reason): void
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

        if ($this->state->equals(ConnectionState::closed())
            || $this->connection !== $connection
        ) {
            $this->logDebug('IGNORED (state: %s, internal conn.ID: {1:B}, conn.ID: %s): TCP connection to [%s] closed',
                $this->state,
                null === $this->connection ? UuidGenerator::empty() : $this->connection->connectionId(),
                $connection->connectionId(),
                $connection->remoteEndPoint()
            );

            return;
        }

        $this->state = ConnectionState::connecting();
        $this->connectingPhase = ConnectingPhase::reconnecting();

        $this->logDebug('TCP connection to [%s, %s] closed', $connection->remoteEndPoint(), $connection->connectionId());

        $this->subscriptions->purgeSubscribedAndDroppedSubscriptions($this->connection->connectionId());

        if (null === $this->reconnInfo) {
            $this->reconnInfo = new ReconnectionInfo(0, $this->stopWatch->elapsed());
        } else {
            $this->reconnInfo = new ReconnectionInfo($this->reconnInfo->reconnectionAttempt(), $this->stopWatch->elapsed());
        }

        if ($this->compareWasConnected(false, true)) {
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
                null === $this->connection ? UuidGenerator::empty() : $this->connection->connectionId(),
                $connection->connectionId(),
                $connection->isClosed() ? 'yes' : 'no',
                $connection->remoteEndPoint()
            );

            return;
        }

        $this->logDebug('TCP connection to [%s, %s] established', $connection->remoteEndPoint(), $connection->connectionId());
        $elapsed = $this->stopWatch->elapsed();

        $this->heartbeatInfo = new HeartbeatInfo($this->packageNumber, true, $elapsed);

        if ($this->settings->defaultUserCredentials() !== null) {
            $this->connectingPhase = ConnectingPhase::authentication();

            $this->authInfo = new AuthInfo(UuidGenerator::generate(), $elapsed);

            $login = null;
            $pass = null;

            if ($this->settings->defaultUserCredentials()) {
                $login = $this->settings->defaultUserCredentials()->username();
                $pass = $this->settings->defaultUserCredentials()->password();
            }

            $this->connection->send(new TcpPackage(
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
        $this->identityInfo = new IdentifyInfo(UuidGenerator::generate(), $this->stopWatch->elapsed());

        $message = new IdentifyClient();
        $message->setVersion(self::ClientVersion);
        $message->setConnectionName($this->esConnection->connectionName());

        $this->connection->send(new TcpPackage(
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

        $this->compareWasConnected(true, false);

        $this->raiseConnectedEvent($this->connection->remoteEndPoint());

        // @todo this leeds to a delay after first connection
        //if ($this->stopWatch->elapsed() - $this->lastTimeoutsTimeStamp >= $this->settings->operationTimeoutCheckPeriod()) {
        $this->operations->checkTimeoutsAndRetry($this->connection);
        $this->subscriptions->checkTimeoutsAndRetry($this->connection);
        $this->lastTimeoutsTimeStamp = $this->stopWatch->elapsed();
        //}
    }

    private function timerTick(): void
    {
        $elapsed = $this->stopWatch->elapsed();

        switch ($this->state->value()) {
            case ConnectionState::Init:
                break;
            case ConnectionState::Connecting:
                if ($this->connectingPhase->equals(ConnectingPhase::reconnecting())
                    && $elapsed - $this->reconnInfo->timestamp() >= $this->settings->reconnectionDelay()
                ) {
                    $this->logDebug('TimerTick checking reconnection...');

                    $this->reconnInfo = new ReconnectionInfo($this->reconnInfo->reconnectionAttempt() + 1, $this->stopWatch->elapsed());

                    if ($this->settings->maxReconnections() >= 0 && $this->reconnInfo->reconnectionAttempt() > $this->settings->maxReconnections()) {
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
                    $msg = 'Timed out waiting for client to be identified';
                    $this->logDebug($msg);
                    $this->closeTcpConnection($msg);
                }

                if ($this->connectingPhase->value() > ConnectingPhase::ConnectionEstablishing) {
                    $this->manageHeartbeats();
                }

                break;
            case ConnectionState::Connected:
                if ($elapsed - $this->lastTimeoutsTimeStamp >= $this->settings->operationTimeoutCheckPeriod()) {
                    $this->reconnInfo = new ReconnectionInfo(0, $elapsed);
                    $this->operations->checkTimeoutsAndRetry($this->connection);
                    $this->subscriptions->checkTimeoutsAndRetry($this->connection);
                    $this->lastTimeoutsTimeStamp = $elapsed;
                }

                $this->manageHeartbeats();

                break;
            case ConnectionState::Closed:
                break;
        }
    }

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
            $this->connection->send(new TcpPackage(
                TcpCommand::heartbeatRequestCommand(),
                TcpFlags::none(),
                UuidGenerator::generate()
            ));

            $this->heartbeatInfo = new HeartbeatInfo($this->heartbeatInfo->lastPackageNumber(), false, $elapsed);
        } else {
            $msg = \sprintf(
                'EventStoreNodeConnection \'%s\': closing TCP connection [%s, %s] due to HEARTBEAT TIMEOUT at pkgNum %s',
                $this->esConnection->connectionName(),
                $this->connection->remoteEndPoint(),
                $this->connection->connectionId(),
                $this->packageNumber
            );

            $this->settings->log()->info($msg);
            $this->closeTcpConnection($msg);
        }
    }

    private function startOperation(ClientOperation $operation, int $maxRetries, int $timeout): void
    {
        switch ($this->state->value()) {
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
                    'StartOperation enqueue %s, %s, %s',
                    $operation->name(),
                    //$operation,
                    $maxRetries,
                    $timeout
                );
                $this->operations->enqueueOperation(new OperationItem($operation, $maxRetries, $timeout));
                break;
            case ConnectionState::Connected:
                $this->logDebug(
                    'StartOperation schedule %s, %s, %s, %s',
                    $operation->name(),
                    $operation,
                    $maxRetries,
                    $timeout
                );
                $this->operations->scheduleOperation(new OperationItem($operation, $maxRetries, $timeout), $this->connection);
                break;
            case ConnectionState::Closed:
                $operation->fail(ConnectionClosedException::withName($this->esConnection->connectionName()));
                break;
        }
    }

    private function startSubscription(StartSubscriptionMessage $message): void
    {
        switch ($this->state->value()) {
            case ConnectionState::Init:
                $message->deferred()->fail(new InvalidOperationException(\sprintf(
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
                    $message->eventAppeared(),
                    $message->subscriptionDropped(),
                    $this->settings->verboseLogging(),
                    function (): TcpPackageConnection {
                        return $this->connection;
                    }
                );

                $this->logDebug(
                    'StartSubscription %s %s, %s, %s, %s',
                    $this->state->equals(ConnectionState::connected()) ? 'fire' : 'enqueue',
                    $operation->name(),
                    $operation,
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
            case ConnectionState::Closed:
                $message->deferred()->fail(ConnectionClosedException::withName($this->esConnection->connectionName()));
                break;
        }
    }

    private function startPersistentSubscription(StartPersistentSubscriptionMessage $message): void
    {
        switch ($this->state->value()) {
            case ConnectionState::Init:
                $message->deferred()->fail(new InvalidOperationException(\sprintf(
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
                    function (): TcpPackageConnection {
                        return $this->connection;
                    }
                );

                $this->logDebug(
                    'StartSubscription %s, %s, %s, %s',
                    //'StartSubscription %s %s, %s, %s, %s',
                    $this->state->equals(ConnectionState::connected()) ? 'fire' : 'enqueue',
                    $operation->name(),
                    //$operation,
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
            case ConnectionState::Closed:
                $message->deferred()->fail(ConnectionClosedException::withName($this->esConnection->connectionName()));
                break;
        }
    }

    private function handleTcpPackage(TcpPackageConnection $connection, TcpPackage $package): void
    {
        if ($this->connection !== $connection
            || $this->state->equals(ConnectionState::closed())
            || $this->state->equals(ConnectionState::init())
        ) {
            $this->logDebug(
                'IGNORED: HandleTcpPackage connId %s, package %s, %s',
                $connection->connectionId(),
                $package->command(),
                $package->correlationId()
            );

            return;
        }

        $this->logDebug(
            'HandleTcpPackage connId %s, package %s, %s',
            $this->connection->connectionId(),
            $package->command(),
            $package->correlationId()
        );

        ++$this->packageNumber;

        if ($package->command()->equals(TcpCommand::heartbeatResponseCommand())) {
            return;
        }

        if ($package->command()->equals(TcpCommand::heartbeatRequestCommand())) {
            $this->connection->send(new TcpPackage(
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
                $result->decision(),
                $result->description(),
                $operation
            );

            switch ($result->decision()->value()) {
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

            if ($this->state->equals(ConnectionState::connected())) {
                $this->operations->tryScheduleWaitingOperations($connection);
            }
        } elseif ($subscription = $this->subscriptions->getActiveSubscription($package->correlationId())) {
            $result = $subscription->operation()->inspectPackage($package);

            $this->logDebug(
                'HandleTcpPackage SUBSCRIPTION DECISION %s (%s), %s',
                $result->decision(),
                $result->description(),
                $operation
            );

            switch ($result->decision()->value()) {
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
                $package->command()
            );
        }
    }

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
            $endPoint,
            $this->connection->remoteEndPoint()
        );

        if ($this->settings->verboseLogging()) {
            $this->settings->log()->info($msg);
        }

        $this->closeTcpConnection($msg);

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

    private function raiseConnectedEvent(IpEndPoint $remoteEndPoint): void
    {
        $this->eventHandler->connected(new ClientConnectionEventArgs($this->esConnection, $remoteEndPoint));
    }

    private function raiseDisconnected(IpEndPoint $remoteEndPoint): void
    {
        $this->eventHandler->disconnected(new ClientConnectionEventArgs($this->esConnection, $remoteEndPoint));
    }

    private function raiseErrorOccurred(\Throwable $e): void
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

    public function detach(ListenerHandler $handler): void
    {
        $this->eventHandler->detach($handler);
    }

    private function compareWasConnected(bool $value, bool $comparand): bool // @todo remove this method
    {
        $original = $this->wasConnected;

        if ($this->wasConnected === $comparand) {
            $this->wasConnected = $value;
        }

        return $original;
    }
}
