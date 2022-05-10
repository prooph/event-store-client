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
use Prooph\EventStore\EventId;
use Prooph\EventStore\EventStoreSubscription;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Exception\MaximumSubscribersReached;
use Prooph\EventStore\Exception\PersistentSubscriptionDeleted;
use Prooph\EventStore\Internal\ConnectToPersistentSubscriptions;
use Prooph\EventStore\Internal\PersistentEventStoreSubscription;
use Prooph\EventStore\PersistentSubscriptionNakEventAction;
use Prooph\EventStore\PersistentSubscriptionResolvedEvent;
use Prooph\EventStore\SubscriptionDropReason;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\Internal\EventMessageConverter;
use Prooph\EventStoreClient\Messages\ClientMessages\ConnectToPersistentSubscription;
use Prooph\EventStoreClient\Messages\ClientMessages\PersistentSubscriptionAckEvents;
use Prooph\EventStoreClient\Messages\ClientMessages\PersistentSubscriptionConfirmation;
use Prooph\EventStoreClient\Messages\ClientMessages\PersistentSubscriptionNakEvents;
use Prooph\EventStoreClient\Messages\ClientMessages\PersistentSubscriptionStreamEventAppeared;
use Prooph\EventStoreClient\Messages\ClientMessages\SubscriptionDropped;
use Prooph\EventStoreClient\Messages\ClientMessages\SubscriptionDropped\SubscriptionDropReason as SubscriptionDropReasonMessage;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Prooph\EventStoreClient\SystemData\TcpFlags;
use Prooph\EventStoreClient\SystemData\TcpPackage;
use Psr\Log\LoggerInterface as Logger;

/** @internal */
class ConnectToPersistentSubscriptionOperation extends AbstractSubscriptionOperation implements ConnectToPersistentSubscriptions
{
    private string $subscriptionId = '';

    public function __construct(
        Logger $logger,
        DeferredFuture $deferred,
        private readonly string $groupName,
        private readonly int $bufferSize,
        string $streamId,
        ?UserCredentials $userCredentials,
        Closure $eventAppeared,
        ?Closure $subscriptionDropped,
        bool $verboseLogging,
        Closure $getConnection
    ) {
        parent::__construct(
            $logger,
            $deferred,
            $streamId,
            false,
            $userCredentials,
            $eventAppeared,
            $subscriptionDropped,
            $verboseLogging,
            $getConnection
        );
    }

    protected function createSubscriptionPackage(): TcpPackage
    {
        $message = new ConnectToPersistentSubscription();
        $message->setEventStreamId($this->streamId);
        $message->setSubscriptionId($this->groupName);
        $message->setAllowedInFlightMessages($this->bufferSize);

        $login = null;
        $pass = null;

        if ($this->userCredentials) {
            $login = $this->userCredentials->username();
            $pass = $this->userCredentials->password();
        }

        return new TcpPackage(
            TcpCommand::ConnectToPersistentSubscription,
            $this->userCredentials ? TcpFlags::Authenticated : TcpFlags::None,
            $this->correlationId,
            $message->serializeToString(),
            $login,
            $pass
        );
    }

    protected function preInspectPackage(TcpPackage $package): ?InspectionResult
    {
        if ($package->command() === TcpCommand::PersistentSubscriptionConfirmation) {
            $message = new PersistentSubscriptionConfirmation();
            $message->mergeFromString($package->data());

            $this->confirmSubscription(
                (int) $message->getLastCommitPosition(),
                $message->getLastEventNumber()
                    ? (int) $message->getLastEventNumber()
                    : null
            );
            $this->subscriptionId = $message->getSubscriptionId();

            return new InspectionResult(InspectionDecision::Subscribed, 'SubscriptionConfirmation');
        }

        if ($package->command() === TcpCommand::PersistentSubscriptionStreamEventAppeared) {
            $message = new PersistentSubscriptionStreamEventAppeared();
            $message->mergeFromString($package->data());

            $event = EventMessageConverter::convertResolvedIndexedEventMessageToResolvedEvent($message->getEvent());
            $this->eventAppeared(new PersistentSubscriptionResolvedEvent($event, $message->getRetryCount()));

            return new InspectionResult(InspectionDecision::DoNothing, 'StreamEventAppeared');
        }

        if ($package->command() === TcpCommand::SubscriptionDropped) {
            $message = new SubscriptionDropped();
            $message->mergeFromString($package->data());

            if ($message->getReason() === SubscriptionDropReasonMessage::AccessDenied) {
                $this->dropSubscription(SubscriptionDropReason::AccessDenied, new AccessDenied('You do not have access to the stream'));

                return new InspectionResult(InspectionDecision::EndOperation, 'SubscriptionDropped');
            }

            if ($message->getReason() === SubscriptionDropReasonMessage::NotFound) {
                $this->dropSubscription(SubscriptionDropReason::NotFound, new InvalidArgumentException('Subscription not found'));

                return new InspectionResult(InspectionDecision::EndOperation, 'SubscriptionDropped');
            }

            if ($message->getReason() === SubscriptionDropReasonMessage::PersistentSubscriptionDeleted) {
                $this->dropSubscription(SubscriptionDropReason::PersistentSubscriptionDeleted, new PersistentSubscriptionDeleted());

                return new InspectionResult(InspectionDecision::EndOperation, 'SubscriptionDropped');
            }

            if ($message->getReason() === SubscriptionDropReasonMessage::SubscriberMaxCountReached) {
                $this->dropSubscription(SubscriptionDropReason::MaxSubscribersReached, new MaximumSubscribersReached());

                return new InspectionResult(InspectionDecision::EndOperation, 'SubscriptionDropped');
            }

            $this->dropSubscription(SubscriptionDropReason::from($message->getReason()), null, ($this->getConnection)());

            return new InspectionResult(InspectionDecision::EndOperation, 'SubscriptionDropped');
        }

        return null;
    }

    protected function createSubscriptionObject(int $lastCommitPosition, ?int $lastEventNumber): EventStoreSubscription
    {
        return new PersistentEventStoreSubscription(
            $this,
            $this->streamId,
            $lastCommitPosition,
            $lastEventNumber
        );
    }

    /** @param list<EventId> $eventIds */
    public function notifyEventsProcessed(array $eventIds): void
    {
        if (empty($eventIds)) {
            throw new InvalidArgumentException('EventIds cannot be empty');
        }

        $message = new PersistentSubscriptionAckEvents();
        $message->setSubscriptionId($this->subscriptionId);
        $message->setProcessedEventIds(\array_map(
            fn (EventId $eventId): string => $eventId->toBinary(),
            $eventIds
        ));

        $login = null;
        $pass = null;

        if ($this->userCredentials) {
            $login = $this->userCredentials->username();
            $pass = $this->userCredentials->password();
        }

        $package = new TcpPackage(
            TcpCommand::PersistentSubscriptionAckEvents,
            $this->userCredentials ? TcpFlags::Authenticated : TcpFlags::None,
            $this->correlationId,
            $message->serializeToString(),
            $login,
            $pass
        );

        $this->enqueueSend($package);
    }

    /**
     * @param list<EventId> $eventIds
     * @param PersistentSubscriptionNakEventAction $action
     * @param string $reason
     */
    public function notifyEventsFailed(
        array $eventIds,
        PersistentSubscriptionNakEventAction $action,
        string $reason
    ): void {
        if (empty($eventIds)) {
            throw new InvalidArgumentException('EventIds cannot be empty');
        }

        $eventIds = \array_map(
            fn (EventId $eventId): string => $eventId->toBinary(),
            $eventIds
        );

        $message = new PersistentSubscriptionNakEvents();
        $message->setSubscriptionId($this->subscriptionId);
        $message->setMessage($reason);
        $message->setAction($action->value);
        $message->setProcessedEventIds($eventIds);

        $login = null;
        $pass = null;

        if ($this->userCredentials) {
            $login = $this->userCredentials->username();
            $pass = $this->userCredentials->password();
        }

        $package = new TcpPackage(
            TcpCommand::PersistentSubscriptionNakEvents,
            $this->userCredentials ? TcpFlags::Authenticated : TcpFlags::None,
            $this->correlationId,
            $message->serializeToString(),
            $login,
            $pass
        );

        $this->enqueueSend($package);
    }

    public function name(): string
    {
        return 'ConnectToPersistentSubscription';
    }

    public function __toString(): string
    {
        return \sprintf(
            'StreamId: %s, ResolveLinkTos: %s, GroupName: %s, BufferSize: %d, SubscriptionId: %s',
            $this->streamId,
            $this->resolveLinkTos ? 'yes' : 'no',
            $this->groupName,
            $this->bufferSize,
            $this->subscriptionId
        );
    }
}
