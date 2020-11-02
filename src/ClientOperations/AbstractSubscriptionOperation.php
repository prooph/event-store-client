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

namespace Prooph\EventStoreClient\ClientOperations;

use Exception;
use function Amp\call;
use Amp\Deferred;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;
use Closure;
use Generator;
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
use SplQueue;
use Throwable;

/** @internal  */
abstract class AbstractSubscriptionOperation implements SubscriptionOperation
{
    private Logger $log;
    private Deferred $deferred;
    protected string $streamId;
    protected bool $resolveLinkTos;
    protected ?UserCredentials $userCredentials;
    protected Closure $eventAppeared;
    private ?Closure $subscriptionDropped;
    private bool $verboseLogging;
    protected Closure $getConnection;
    private int $maxQueueSize = 2000;
    private SplQueue $actionQueue;
    private ?EventStoreSubscription $subscription = null;
    private bool $unsubscribed = false;
    protected string $correlationId = '';

    public function __construct(
        Logger $logger,
        Deferred $deferred,
        string $streamId,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials,
        Closure $eventAppeared,
        ?Closure $subscriptionDropped,
        bool $verboseLogging,
        Closure $getConnection
    ) {
        $this->log = $logger;
        $this->deferred = $deferred;
        $this->streamId = $streamId;
        $this->resolveLinkTos = $resolveLinkTos;
        $this->userCredentials = $userCredentials;
        $this->eventAppeared = $eventAppeared;
        $this->subscriptionDropped = $subscriptionDropped;
        $this->verboseLogging = $verboseLogging;
        $this->getConnection = $getConnection;
        $this->actionQueue = new SplQueue();
    }

    protected function enqueueSend(TcpPackage $package): void
    {
        $connection = ($this->getConnection)();

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
        $this->dropSubscription(SubscriptionDropReason::userInitiated(), null, ($this->getConnection)());
    }

    private function createUnsubscriptionPackage(): TcpPackage
    {
        return new TcpPackage(
            TcpCommand::unsubscribeFromStream(),
            TcpFlags::none(),
            $this->correlationId,
            (string) (new UnsubscribeFromStream())->serializeToString()
        );
    }

    abstract protected function preInspectPackage(TcpPackage $package): ?InspectionResult;

    public function inspectPackage(TcpPackage $package): InspectionResult
    {
        try {
            if ($result = $this->preInspectPackage($package)) {
                return $result;
            }

            switch ($package->command()->value()) {
                case TcpCommand::SUBSCRIPTION_DROPPED:
                    $message = new SubscriptionDroppedMessage();
                    $message->mergeFromString($package->data());

                    switch ($message->getReason()) {
                        case SubscriptionDropReasonMessage::Unsubscribed:
                            $this->dropSubscription(SubscriptionDropReason::userInitiated(), null);
                            break;
                        case SubscriptionDropReasonMessage::AccessDenied:
                            $this->dropSubscription(SubscriptionDropReason::accessDenied(), new AccessDenied(\sprintf(
                                'Subscription to \'%s\' failed due to access denied',
                                $this->streamId
                            )));
                            break;
                        case SubscriptionDropReasonMessage::NotFound:
                            $this->dropSubscription(SubscriptionDropReason::notFound(), new RuntimeException(\sprintf(
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
                            $this->dropSubscription(SubscriptionDropReason::unknown(), new UnexpectedCommand(
                                'Unsubscribe reason: ' . $message->getReason()
                            ));
                            break;
                    }

                    return new InspectionResult(InspectionDecision::endOperation(), 'SubscriptionDropped: ' . $message->getReason());
                case TcpCommand::NOT_AUTHENTICATED_EXCEPTION:
                    $this->dropSubscription(SubscriptionDropReason::notAuthenticated(), new NotAuthenticated());

                    return new InspectionResult(InspectionDecision::endOperation(), 'NotAuthenticated');
                case TcpCommand::BAD_REQUEST:
                    $this->dropSubscription(SubscriptionDropReason::serverError(), new ServerError());

                    return new InspectionResult(InspectionDecision::endOperation(), 'BadRequest');
                case TcpCommand::NOT_HANDLED:
                    if (null !== $this->subscription) {
                        throw new \Exception('NotHandledException command appeared while we were already subscribed');
                    }

                    $message = new NotHandled();
                    $message->mergeFromString($package->data());

                    switch ($message->getReason()) {
                        case NotHandledReason::NotReady:
                            return new InspectionResult(InspectionDecision::retry(), 'NotHandledException - NotReady');
                        case NotHandledReason::TooBusy:
                            return new InspectionResult(InspectionDecision::retry(), 'NotHandledException - TooBusy');
                        case NotHandledReason::NotMaster:
                            $masterInfo = new MasterInfo();
                            $masterInfo->mergeFromString($message->getAdditionalInfo());

                            return new InspectionResult(
                                InspectionDecision::reconnect(),
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

                            return new InspectionResult(InspectionDecision::retry(), 'NotHandledException - <unknown>');
                    }

                    break;
                default:
                    $this->dropSubscription(
                        SubscriptionDropReason::serverError(),
                        UnexpectedCommand::withName($package->command()->name())
                    );

                    return new InspectionResult(InspectionDecision::endOperation(), $package->command()->name());

            }
        } catch (\Exception $e) {
            $this->dropSubscription(SubscriptionDropReason::unknown(), $e);

            return new InspectionResult(InspectionDecision::endOperation(), 'Exception - ' . $e->getMessage());
        }
    }

    public function connectionClosed(): void
    {
        $this->dropSubscription(
            SubscriptionDropReason::connectionClosed(),
            new ConnectionClosed('Connection was closed')
        );
    }

    /** @internal */
    public function timeOutSubscription(): bool
    {
        if (null !== $this->subscription) {
            return false;
        }

        $this->dropSubscription(SubscriptionDropReason::subscribingError(), null);

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

            if (! $reason->equals(SubscriptionDropReason::userInitiated())) {
                $exception ??= new \Exception('Subscription dropped for ' . $reason);

                try {
                    $this->deferred->fail($exception);
                } catch (\Error $e) {
                    // ignore already failed promises
                }
            }

            if ($reason->equals(SubscriptionDropReason::userInitiated())
                 && null !== $this->subscription
                 && null !== $connection
             ) {
                $connection->enqueueSend($this->createUnsubscriptionPackage());
            }

            if (null !== $this->subscription) {
                $this->executeActionAsync(function () use ($reason, $exception) {
                    if ($this->subscriptionDropped) {
                        ($this->subscriptionDropped)($this->subscription, $reason, $exception);
                    }

                    return new Success();
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
        $this->deferred->resolve($this->subscription);
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

        $this->executeActionAsync(fn (): Promise => ($this->eventAppeared)($this->subscription, $e));
    }

    private function executeActionAsync(Closure $action): void
    {
        $this->actionQueue->enqueue($action);

        if ($this->actionQueue->count() > $this->maxQueueSize) {
            $this->dropSubscription(SubscriptionDropReason::userInitiated(), new Exception('client buffer too big'));
        }

        Loop::defer(function (): Generator {
            yield $this->executeActions();
        });
    }

    /** @return Promise<void> */
    private function executeActions(): Promise
    {
        return call(function (): Generator {
            while (! $this->actionQueue->isEmpty()) {
                $action = $this->actionQueue->dequeue();
                \assert($action instanceof Closure);

                try {
                    yield $action();
                } catch (Exception $exception) {
                    $this->log->error(\sprintf(
                        'Exception during executing user callback: %s', $exception->getMessage()
                    ));
                }
            }
        });
    }
}
