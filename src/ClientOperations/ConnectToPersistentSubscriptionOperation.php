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

use Amp\Deferred;
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
    private string $groupName;
    private int $bufferSize;
    private string $subscriptionId = '';

    public function __construct(
        Logger $logger,
        Deferred $deferred,
        string $groupName,
        int $bufferSize,
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

        $this->groupName = $groupName;
        $this->bufferSize = $bufferSize;
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
            TcpCommand::connectToPersistentSubscription(),
            $this->userCredentials ? TcpFlags::authenticated() : TcpFlags::none(),
            $this->correlationId,
            $message->serializeToString(),
            $login,
            $pass
        );
    }

    protected function preInspectPackage(TcpPackage $package): ?InspectionResult
    {
        if ($package->command()->equals(TcpCommand::persistentSubscriptionConfirmation())) {
            $message = new PersistentSubscriptionConfirmation();
            $message->mergeFromString($package->data());

            $this->confirmSubscription($message->getLastCommitPosition(), $message->getLastEventNumber());
            $this->subscriptionId = $message->getSubscriptionId();

            return new InspectionResult(InspectionDecision::subscribed(), 'SubscriptionConfirmation');
        }

        if ($package->command()->equals(TcpCommand::persistentSubscriptionStreamEventAppeared())) {
            $message = new PersistentSubscriptionStreamEventAppeared();
            $message->mergeFromString($package->data());

            $event = EventMessageConverter::convertResolvedIndexedEventMessageToResolvedEvent($message->getEvent());
            $this->eventAppeared(new PersistentSubscriptionResolvedEvent($event, $message->getRetryCount()));

            return new InspectionResult(InspectionDecision::doNothing(), 'StreamEventAppeared');
        }

        if ($package->command()->equals(TcpCommand::subscriptionDropped())) {
            $message = new SubscriptionDropped();
            $message->mergeFromString($package->data());

            if ($message->getReason() === SubscriptionDropReasonMessage::AccessDenied) {
                $this->dropSubscription(SubscriptionDropReason::accessDenied(), new AccessDenied('You do not have access to the stream'));

                return new InspectionResult(InspectionDecision::endOperation(), 'SubscriptionDropped');
            }

            if ($message->getReason() === SubscriptionDropReasonMessage::NotFound) {
                $this->dropSubscription(SubscriptionDropReason::notFound(), new InvalidArgumentException('Subscription not found'));

                return new InspectionResult(InspectionDecision::endOperation(), 'SubscriptionDropped');
            }

            if ($message->getReason() === SubscriptionDropReasonMessage::PersistentSubscriptionDeleted) {
                $this->dropSubscription(SubscriptionDropReason::persistentSubscriptionDeleted(), new PersistentSubscriptionDeleted());

                return new InspectionResult(InspectionDecision::endOperation(), 'SubscriptionDropped');
            }

            if ($message->getReason() === SubscriptionDropReasonMessage::SubscriberMaxCountReached) {
                $this->dropSubscription(SubscriptionDropReason::maxSubscribersReached(), new MaximumSubscribersReached());

                return new InspectionResult(InspectionDecision::endOperation(), 'SubscriptionDropped');
            }

            $this->dropSubscription(SubscriptionDropReason::byValue($message->getReason()), null, ($this->getConnection)());

            return new InspectionResult(InspectionDecision::endOperation(), 'SubscriptionDropped');
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

    /** @param EventId[] $eventIds */
    public function notifyEventsProcessed(array $eventIds): void
    {
        if (empty($eventIds)) {
            throw new InvalidArgumentException('EventIds cannot be empty');
        }

        $eventIds = \array_map(
            fn (EventId $eventId): string => $eventId->toBinary(),
            $eventIds
        );

        $message = new PersistentSubscriptionAckEvents();
        $message->setSubscriptionId($this->subscriptionId);
        $message->setProcessedEventIds($eventIds);

        $login = null;
        $pass = null;

        if ($this->userCredentials) {
            $login = $this->userCredentials->username();
            $pass = $this->userCredentials->password();
        }

        $package = new TcpPackage(
            TcpCommand::persistentSubscriptionAckEvents(),
            $this->userCredentials ? TcpFlags::authenticated() : TcpFlags::none(),
            $this->correlationId,
            $message->serializeToString(),
            $login,
            $pass
        );

        $this->enqueueSend($package);
    }

    /**
     * @param EventId[] $eventIds
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
        $message->setAction($action->value());
        $message->setProcessedEventIds($eventIds);

        $login = null;
        $pass = null;

        if ($this->userCredentials) {
            $login = $this->userCredentials->username();
            $pass = $this->userCredentials->password();
        }

        $package = new TcpPackage(
            TcpCommand::persistentSubscriptionNakEvents(),
            $this->userCredentials ? TcpFlags::authenticated() : TcpFlags::none(),
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
