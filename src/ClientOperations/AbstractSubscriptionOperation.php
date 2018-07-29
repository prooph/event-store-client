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

namespace Prooph\EventStoreClient\ClientOperations;

use Amp\Deferred;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;
use Generator;
use Prooph\EventStoreClient\EventStoreSubscription;
use Prooph\EventStoreClient\Exception\AccessDeniedException;
use Prooph\EventStoreClient\Exception\ConnectionClosedException;
use Prooph\EventStoreClient\Exception\NotAuthenticatedException;
use Prooph\EventStoreClient\Exception\RuntimeException;
use Prooph\EventStoreClient\Exception\ServerError;
use Prooph\EventStoreClient\Exception\UnexpectedCommandException;
use Prooph\EventStoreClient\Internal\ResolvedEvent;
use Prooph\EventStoreClient\IpEndPoint;
use Prooph\EventStoreClient\Messages\ClientMessages\NotHandled;
use Prooph\EventStoreClient\Messages\ClientMessages\NotHandled\MasterInfo;
use Prooph\EventStoreClient\Messages\ClientMessages\NotHandled\NotHandledReason;
use Prooph\EventStoreClient\Messages\ClientMessages\SubscriptionDropped;
use Prooph\EventStoreClient\Messages\ClientMessages\SubscriptionDropped\SubscriptionDropReason as SubscriptionDropReasonMessage;
use Prooph\EventStoreClient\Messages\ClientMessages\UnsubscribeFromStream;
use Prooph\EventStoreClient\SubscriptionDropReason;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Prooph\EventStoreClient\SystemData\TcpFlags;
use Prooph\EventStoreClient\SystemData\TcpPackage;
use Prooph\EventStoreClient\Transport\Tcp\TcpPackageConnection;
use Prooph\EventStoreClient\UserCredentials;
use Psr\Log\LoggerInterface as Logger;
use SplQueue;
use Throwable;
use function Amp\call;

/** @internal  */
abstract class AbstractSubscriptionOperation implements SubscriptionOperation
{
    /** @var Logger */
    private $log;
    /** @var Deferred */
    private $deferred;
    /** @var string */
    protected $streamId;
    /** @var bool */
    protected $resolveLinkTos;
    /** @var UserCredentials|null */
    protected $userCredentials;
    /** @var callable(\Prooph\EventStoreClient\EventStoreSubscription $subscription, \Prooph\EventStoreClient\Internal\ResolvedEvent $resolvedEvent): Promise */
    protected $eventAppeared;
    /** @var null|callable(\Prooph\EventStoreClient\EventStoreSubscription $subscription, \Prooph\EventStoreClient\SubscriptionDropReason $reason, \Throwable $exception): void */
    private $subscriptionDropped;
    /** @var bool */
    private $verboseLogging;
    /** @var callable(?\Prooph\EventStoreClient\Transport\Tcp\TcpPackageConnection $connection) */
    protected $getConnection;
    /** @var int */
    private $maxQueueSize = 2000;
    /** @var SplQueue */
    private $actionQueue;
    /** @var bool */
    private $actionExecuting = false;
    /** @var EventStoreSubscription */
    private $subscription;
    /** @var bool */
    private $unsubscribed = false;
    /** @var string */
    protected $correlationId;

    /**
     * @param Logger $logger
     * @param Deferred $deferred
     * @param string $streamId
     * @param bool $resolveLinkTos
     * @param null|UserCredentials $userCredentials
     * @param callable(\Prooph\EventStoreClient\EventStoreSubscription $subscription, \Prooph\EventStoreClient\Internal\ResolvedEvent $resolvedEvent): Promise $eventAppeared
     * @param null|callable(\Prooph\EventStoreClient\EventStoreSubscription $subscription, \Prooph\EventStoreClient\SubscriptionDropReason $reason, \Throwable $exception): void $subscriptionDropped
     * @param bool $verboseLogging
     * @param callable $getConnection
     */
    public function __construct(
        Logger $logger,
        Deferred $deferred,
        string $streamId,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials,
        callable $eventAppeared,
        ?callable $subscriptionDropped,
        bool $verboseLogging,
        callable $getConnection
    ) {
        $this->log = $logger;
        $this->deferred = $deferred;
        $this->streamId = $streamId;
        $this->resolveLinkTos = $resolveLinkTos;
        $this->userCredentials = $userCredentials;
        $this->eventAppeared = $eventAppeared;
        $this->subscriptionDropped = $subscriptionDropped ?? function (): void {
        };
        $this->verboseLogging = $verboseLogging;
        $this->getConnection = $getConnection;
        $this->actionQueue = new SplQueue();
    }

    protected function enqueueSend(TcpPackage $package): void
    {
        ($this->getConnection)()->send($package);
    }

    public function subscribe(string $correlationId, TcpPackageConnection $connection): bool
    {
        if (null !== $this->subscription || $this->unsubscribed) {
            return false;
        }

        $this->correlationId = $correlationId;

        $connection->send($this->createSubscriptionPackage());

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

            switch ($package->command()->value()) {
                case TcpCommand::SubscriptionDropped:
                    $message = new SubscriptionDropped();
                    $message->mergeFromString($package->data());

                    switch ($message->getReason()) {
                        case SubscriptionDropReasonMessage::Unsubscribed:
                            $this->dropSubscription(SubscriptionDropReason::userInitiated(), null);
                            break;
                        case SubscriptionDropReasonMessage::AccessDenied:
                            $this->dropSubscription(SubscriptionDropReason::accessDenied(), new AccessDeniedException(\sprintf(
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
                            $this->dropSubscription(SubscriptionDropReason::unknown(), new UnexpectedCommandException(
                                'Unsubscribe reason: ' . $message->getReason()
                            ));
                            break;
                    }

                    return new InspectionResult(InspectionDecision::endOperation(), 'SubscriptionDropped: ' . $message->getReason());
                case TcpCommand::NotAuthenticatedException:
                    $this->dropSubscription(SubscriptionDropReason::notAuthenticated(), new NotAuthenticatedException());

                    return new InspectionResult(InspectionDecision::endOperation(), 'NotAuthenticated');
                case TcpCommand::BadRequest:
                    $this->dropSubscription(SubscriptionDropReason::serverError(), new ServerError());

                    return new InspectionResult(InspectionDecision::endOperation(), 'BadRequest');
                case TcpCommand::NotHandled:
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
                                new IpEndPoint(
                                    $masterInfo->getExternalTcpAddress(),
                                    $masterInfo->getExternalTcpPort()
                                ),
                                new IpEndPoint(
                                    $masterInfo->getExternalSecureTcpAddress(),
                                    $masterInfo->getExternalSecureTcpPort()
                                )
                            );
                        default:
                            $this->log->error('Unknown NotHandledReason: %s', $message->getReason());

                            return new InspectionResult(InspectionDecision::retry(), 'NotHandledException - <unknown>');
                    }

                    break;
                default:
                    $this->dropSubscription(
                        SubscriptionDropReason::serverError(),
                        UnexpectedCommandException::withName($package->command()->name())
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
            new ConnectionClosedException('Connection was closed')
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
        Throwable $exception = null,
        TcpPackageConnection $connection = null
    ): void {
        if (! $this->unsubscribed) {
            $this->unsubscribed = true;

            if ($this->verboseLogging) {
                $this->log->debug(\sprintf(
                    'Subscription %s to %s: closing subscription, reason: %s, exception: %s...',
                    $this->correlationId,
                    $this->streamId ? $this->streamId : '<all>',
                    $reason,
                    $exception ? $exception->getMessage() : '<none>'
                ));
            }

            if (! $reason->equals(SubscriptionDropReason::userInitiated())) {
                $exception = $exception ?? new \Exception('Subscription dropped for ' . $reason);

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
                $connection->send($this->createUnsubscriptionPackage());
            }

            if (null !== $this->subscription) {
                $this->executeActionAsync(function () use ($reason, $exception) {
                    ($this->subscriptionDropped)($this->subscription, $reason, $exception);

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
            $this->log->debug(\sprintf(
                'Subscription %s to %s: event appeared (%s, %d, %s @ %s)',
                $this->correlationId,
                $this->streamId ? $this->streamId : '<all>',
                $e->originalStreamName(),
                $e->originalEventNumber(),
                $e->originalEvent()->eventType(),
                $e->originalPosition() ?? '<null>'
            ));
        }

        $this->executeActionAsync(function () use ($e): Promise {
            return ($this->eventAppeared)($this->subscription, $e);
        });
    }

    private function executeActionAsync(callable $action): void
    {
        $this->actionQueue->enqueue($action);

        if ($this->actionQueue->count() > $this->maxQueueSize) {
            $this->dropSubscription(SubscriptionDropReason::userInitiated(), new \Exception('client buffer too big'));
        }

        if (! $this->actionExecuting) {
            $this->actionExecuting = true;

            Loop::defer(function (): Generator {
                yield $this->executeActions();
            });
        }
    }

    /** @return Promise<void> */
    private function executeActions(): Promise
    {
        return call(function (): Generator {
            while (! $this->actionQueue->isEmpty() && $this->actionExecuting) {
                /** @var callable $action */
                $action = $this->actionQueue->dequeue();

                try {
                    yield $action();
                } catch (Throwable $exception) {
                    $this->log->error(\sprintf(
                        'Exception during executing user callback: %s', $exception->getMessage()
                    ));
                }

                $this->actionExecuting = false;
            }
        });
    }
}
