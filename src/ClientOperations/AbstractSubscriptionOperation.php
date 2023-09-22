<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2022 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2022 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\ClientOperations;

use Amp\DeferredFuture;
use Closure;
use Exception;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\EventStoreSubscription;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\ConnectionClosed;
use Prooph\EventStore\Exception\NotAuthenticated;
use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStore\Exception\ServerError;
use Prooph\EventStore\Exception\UnexpectedCommand;
use Prooph\EventStore\Internal\ResolvedEvent;
use Prooph\EventStore\SubscriptionDropReason;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\Messages\ClientMessages\NotHandled;
use Prooph\EventStoreClient\Messages\ClientMessages\NotHandled\MasterInfo;
use Prooph\EventStoreClient\Messages\ClientMessages\NotHandled\NotHandledReason;
use Prooph\EventStoreClient\Messages\ClientMessages\SubscriptionDropped as SubscriptionDroppedMessage;
use Prooph\EventStoreClient\Messages\ClientMessages\SubscriptionDropped\SubscriptionDropReason as SubscriptionDropReasonMessage;
use Prooph\EventStoreClient\Messages\ClientMessages\UnsubscribeFromStream;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Prooph\EventStoreClient\SystemData\TcpFlags;
use Prooph\EventStoreClient\SystemData\TcpPackage;
use Prooph\EventStoreClient\Transport\Tcp\TcpPackageConnection;
use Psr\Log\LoggerInterface as Logger;
use Revolt\EventLoop;
use SplQueue;
use Throwable;

/** @internal  */
abstract class AbstractSubscriptionOperation implements SubscriptionOperation
{
    private const MaxQueueSize = 2000;

    private readonly SplQueue $actionQueue;

    private ?EventStoreSubscription $subscription = null;

    private bool $unsubscribed = false;

    protected string $correlationId = '';

    public function __construct(
        private readonly Logger $log,
        private readonly DeferredFuture $deferred,
        protected readonly string $streamId,
        protected readonly bool $resolveLinkTos,
        protected readonly ?UserCredentials $userCredentials,
        protected readonly Closure $eventAppeared,
        private readonly ?Closure $subscriptionDropped,
        private readonly bool $verboseLogging,
        protected readonly Closure $getConnection
    ) {
        $this->actionQueue = new SplQueue();
    }

    protected function enqueueSend(TcpPackage $package): void
    {
        $connection = ($this->getConnection)();

        // In rare case the connection can be null. It is race condition where the connection was just closed.
        if (null !== $connection) {
            $connection->enqueueSend($package);
        }
    }

    public function subscribe(string $correlationId, TcpPackageConnection $connection): bool
    {
        if (null !== $this->subscription || $this->unsubscribed) {
            return false;
        }

        $this->correlationId = $correlationId;

        $connection->enqueueSend($this->createSubscriptionPackage());

        return true;
    }

    abstract protected function createSubscriptionPackage(): TcpPackage;

    public function unsubscribe(): void
    {
        $this->dropSubscription(SubscriptionDropReason::UserInitiated, null, ($this->getConnection)());
    }

    private function createUnsubscriptionPackage(): TcpPackage
    {
        return new TcpPackage(
            TcpCommand::UnsubscribeFromStream,
            TcpFlags::None,
            $this->correlationId,
            (new UnsubscribeFromStream())->serializeToString()
        );
    }

    abstract protected function preInspectPackage(TcpPackage $package): ?InspectionResult;

    public function inspectPackage(TcpPackage $package): InspectionResult
    {
        try {
            if ($result = $this->preInspectPackage($package)) {
                return $result;
            }

            switch ($package->command()) {
                case TcpCommand::SubscriptionDropped:
                    $message = new SubscriptionDroppedMessage();
                    $message->mergeFromString($package->data());

                    switch ($message->getReason()) {
                        case SubscriptionDropReasonMessage::Unsubscribed:
                            $this->dropSubscription(SubscriptionDropReason::UserInitiated, null);

                            break;
                        case SubscriptionDropReasonMessage::AccessDenied:
                            $this->dropSubscription(SubscriptionDropReason::AccessDenied, new AccessDenied(\sprintf(
                                'Subscription to \'%s\' failed due to access denied',
                                $this->streamId
                            )));

                            break;
                        case SubscriptionDropReasonMessage::NotFound:
                            $this->dropSubscription(SubscriptionDropReason::NotFound, new RuntimeException(\sprintf(
                                'Subscription to \'%s\' failed due to not found',
                                $this->streamId
                            )));

                            break;
                        default:
                            if ($this->verboseLogging) {
                                $this->log->debug(\sprintf(
                                    'Subscription dropped by server. Reason: %s',
                                    $message->getReason()
                                ));
                            }
                            $this->dropSubscription(SubscriptionDropReason::Unknown, new UnexpectedCommand(
                                'Unsubscribe reason: ' . $message->getReason()
                            ));

                            break;
                    }

                    return new InspectionResult(InspectionDecision::EndOperation, 'SubscriptionDropped: ' . $message->getReason());
                case TcpCommand::NotAuthenticatedException:
                    $this->dropSubscription(SubscriptionDropReason::NotAuthenticated, new NotAuthenticated());

                    return new InspectionResult(InspectionDecision::EndOperation, 'NotAuthenticated');
                case TcpCommand::BadRequest:
                    $this->dropSubscription(SubscriptionDropReason::ServerError, new ServerError());

                    return new InspectionResult(InspectionDecision::EndOperation, 'BadRequest');
                case TcpCommand::NotHandled:
                    if (null !== $this->subscription) {
                        throw new \Exception('NotHandledException command appeared while we were already subscribed');
                    }

                    $message = new NotHandled();
                    $message->mergeFromString($package->data());

                    switch ($message->getReason()) {
                        case NotHandledReason::NotReady:
                            return new InspectionResult(InspectionDecision::Retry, 'NotHandledException - NotReady');
                        case NotHandledReason::TooBusy:
                            return new InspectionResult(InspectionDecision::Retry, 'NotHandledException - TooBusy');
                        case NotHandledReason::NotMaster:
                            $masterInfo = new MasterInfo();
                            $masterInfo->mergeFromString($message->getAdditionalInfo());

                            return new InspectionResult(
                                InspectionDecision::Reconnect,
                                'NotHandledException - NotMaster',
                                new EndPoint(
                                    $masterInfo->getExternalTcpAddress(),
                                    $masterInfo->getExternalTcpPort()
                                ),
                                new EndPoint(
                                    $masterInfo->getExternalSecureTcpAddress(),
                                    $masterInfo->getExternalSecureTcpPort()
                                )
                            );
                        default:
                            $this->log->error(\sprintf(
                                'Unknown NotHandledReason: %s',
                                $message->getReason()
                            ));

                            return new InspectionResult(InspectionDecision::Retry, 'NotHandledException - <unknown>');
                    }

                    break;
                default:
                    $this->dropSubscription(
                        SubscriptionDropReason::ServerError,
                        UnexpectedCommand::with($package->command())
                    );

                    return new InspectionResult(InspectionDecision::EndOperation, $package->command()->name);

            }
        } catch (\Exception $e) {
            $this->dropSubscription(SubscriptionDropReason::Unknown, $e);

            return new InspectionResult(InspectionDecision::EndOperation, 'Exception - ' . $e->getMessage());
        }
    }

    public function connectionClosed(): void
    {
        $this->dropSubscription(
            SubscriptionDropReason::ConnectionClosed,
            new ConnectionClosed('Connection was closed')
        );
    }

    /** @internal */
    public function timeOutSubscription(): bool
    {
        if (null !== $this->subscription) {
            return false;
        }

        $this->dropSubscription(SubscriptionDropReason::SubscribingError, null);

        return true;
    }

    public function dropSubscription(
        SubscriptionDropReason $reason,
        ?Throwable $exception = null,
        ?TcpPackageConnection $connection = null
    ): void {
        if (! $this->unsubscribed) {
            $this->unsubscribed = true;

            if ($this->verboseLogging) {
                $this->log->debug(\sprintf(
                    'Subscription %s to %s: closing subscription, reason: %s, exception: %s...',
                    $this->correlationId,
                    $this->streamId ? $this->streamId : '<all>',
                    (string) $reason,
                    $exception ? $exception->getMessage() : '<none>'
                ));
            }

            if ($reason !== SubscriptionDropReason::UserInitiated) {
                $exception ??= new \Exception('Subscription dropped for ' . $reason->name);

                try {
                    $this->deferred->error($exception);
                } catch (\Error $e) {
                    // ignore already errored future
                }
            }

            if ($reason === SubscriptionDropReason::UserInitiated
                 && null !== $this->subscription
                 && null !== $connection
             ) {
                $connection->enqueueSend($this->createUnsubscriptionPackage());
            }

            if (null !== $this->subscription) {
                $this->executeAction(function () use ($reason, $exception): void {
                    if ($this->subscriptionDropped) {
                        ($this->subscriptionDropped)($this->subscription, $reason, $exception);
                    }
                });
            }
        }
    }

    protected function confirmSubscription(int $lastCommitPosition, ?int $lastEventNumber): void
    {
        if ($lastCommitPosition < -1) {
            throw new \OutOfRangeException(\sprintf(
                'Invalid lastCommitPosition %s on subscription confirmation',
                $lastCommitPosition
            ));
        }

        if (null !== $this->subscription) {
            throw new \Exception('Double confirmation of subscription');
        }

        if ($this->verboseLogging) {
            $this->log->debug(\sprintf(
                'Subscription %s to %s: subscribed at CommitPosition: %d, EventNumber: %s',
                $this->correlationId,
                $this->streamId ? $this->streamId : '<all>',
                $lastCommitPosition,
                $lastEventNumber ?? '<null>'
            ));
        }

        $this->subscription = $this->createSubscriptionObject($lastCommitPosition, $lastEventNumber);
        $this->deferred->complete($this->subscription);
    }

    abstract protected function createSubscriptionObject(int $lastCommitPosition, ?int $lastEventNumber): EventStoreSubscription;

    protected function eventAppeared(ResolvedEvent $e): void
    {
        if ($this->unsubscribed) {
            return;
        }

        if (null === $this->subscription) {
            throw new RuntimeException('Subscription not confirmed, but event appeared!');
        }

        if ($this->verboseLogging) {
            /** @psalm-suppress PossiblyNullReference */
            $this->log->debug(\sprintf(
                'Subscription %s to %s: event appeared (%s, %d, %s @ %s)',
                $this->correlationId,
                $this->streamId ? $this->streamId : '<all>',
                $e->originalStreamName(),
                $e->originalEventNumber(),
                $e->originalEvent()->eventType(),
                $e->originalPosition() ? $e->originalPosition()->__toString() : '<null>'
            ));
        }

        $this->executeAction(function () use ($e): void {
            ($this->eventAppeared)($this->subscription, $e);
        });
    }

    private function executeAction(Closure $action): void
    {
        $this->actionQueue->enqueue($action);

        if ($this->actionQueue->count() > self::MaxQueueSize) {
            $this->dropSubscription(SubscriptionDropReason::UserInitiated, new Exception('client buffer too big'));
        }

        EventLoop::defer(function (): void {
            while (! $this->actionQueue->isEmpty()) {
                $action = $this->actionQueue->dequeue();
                \assert($action instanceof Closure);

                try {
                    $action();
                } catch (Exception $exception) {
                    $this->log->error(\sprintf(
                        'Exception during executing user callback: %s',
                        $exception->getMessage()
                    ));
                }
            }
        });
    }
}
