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
use Google\Protobuf\Internal\Message;
use Prooph\EventStore\Common\SystemConsumerStrategy;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\InvalidOperationException;
use Prooph\EventStore\Exception\UnexpectedOperationResult;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\PersistentSubscriptionUpdateResult;
use Prooph\EventStore\PersistentSubscriptionUpdateStatus;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\Messages\ClientMessages\UpdatePersistentSubscription;
use Prooph\EventStoreClient\Messages\ClientMessages\UpdatePersistentSubscriptionCompleted;
use Prooph\EventStoreClient\Messages\ClientMessages\UpdatePersistentSubscriptionCompleted\UpdatePersistentSubscriptionResult;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Psr\Log\LoggerInterface as Logger;

/**
 * @internal
 * @extends AbstractOperation<UpdatePersistentSubscriptionCompleted, PersistentSubscriptionUpdateResult>
 */
class UpdatePersistentSubscriptionOperation extends AbstractOperation
{
    public function __construct(
        Logger $logger,
        DeferredFuture $deferred,
        private readonly string $stream,
        private readonly string $groupName,
        private readonly PersistentSubscriptionSettings $settings,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct(
            $logger,
            $deferred,
            $userCredentials,
            TcpCommand::UpdatePersistentSubscription,
            TcpCommand::UpdatePersistentSubscriptionCompleted,
            UpdatePersistentSubscriptionCompleted::class
        );
    }

    protected function createRequestDto(): Message
    {
        $message = new UpdatePersistentSubscription();
        $message->setSubscriptionGroupName($this->groupName);
        $message->setEventStreamId($this->stream);
        $message->setResolveLinkTos($this->settings->resolveLinkTos());
        $message->setStartFrom($this->settings->startFrom());
        $message->setMessageTimeoutMilliseconds($this->settings->messageTimeoutMilliseconds());
        $message->setRecordStatistics($this->settings->extraStatistics());
        $message->setLiveBufferSize($this->settings->liveBufferSize());
        $message->setReadBatchSize($this->settings->readBatchSize());
        $message->setBufferSize($this->settings->bufferSize());
        $message->setMaxRetryCount($this->settings->maxRetryCount());
        $message->setPreferRoundRobin($this->settings->namedConsumerStrategy() === SystemConsumerStrategy::RoundRobin);
        $message->setCheckpointAfterTime($this->settings->checkPointAfterMilliseconds());
        $message->setCheckpointMaxCount($this->settings->maxCheckPointCount());
        $message->setCheckpointMinCount($this->settings->minCheckPointCount());
        $message->setSubscriberMaxCount($this->settings->maxSubscriberCount());
        $message->setNamedConsumerStrategy($this->settings->namedConsumerStrategy()->name);

        return $message;
    }

    /**
     * @param UpdatePersistentSubscriptionCompleted $response
     * @return InspectionResult
     */
    protected function inspectResponse(Message $response): InspectionResult
    {
        switch ($response->getResult()) {
            case UpdatePersistentSubscriptionResult::Success:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::EndOperation, 'Success');
            case UpdatePersistentSubscriptionResult::Fail:
                $this->fail(new InvalidOperationException(\sprintf(
                    'Subscription group \'%s\' on stream \'%s\' failed \'%s\'',
                    $this->groupName,
                    $this->stream,
                    $response->getReason()
                )));

                return new InspectionResult(InspectionDecision::EndOperation, 'Fail');
            case UpdatePersistentSubscriptionResult::AccessDenied:
                $this->fail(AccessDenied::toStream($this->stream));

                return new InspectionResult(InspectionDecision::EndOperation, 'AccessDenied');
            case UpdatePersistentSubscriptionResult::DoesNotExist:
                $this->fail(new InvalidOperationException(\sprintf(
                    'Subscription group \'%s\' on stream \'%s\' does not exist',
                    $this->groupName,
                    $this->stream
                )));

                return new InspectionResult(InspectionDecision::EndOperation, 'DoesNotExist');
            default:
                throw new UnexpectedOperationResult();
        }
    }

    /**
     * @param UpdatePersistentSubscriptionCompleted $response
     * @return PersistentSubscriptionUpdateResult
     */
    protected function transformResponse(Message $response): PersistentSubscriptionUpdateResult
    {
        return new PersistentSubscriptionUpdateResult(
            PersistentSubscriptionUpdateStatus::Success
        );
    }

    public function name(): string
    {
        return 'UpdatePersistentSubscription';
    }

    public function __toString(): string
    {
        return \sprintf('Stream: %s, Group Name: %s', $this->stream, $this->groupName);
    }
}
