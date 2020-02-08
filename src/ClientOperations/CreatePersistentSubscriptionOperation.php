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
use Google\Protobuf\Internal\Message;
use Prooph\EventStore\Common\SystemConsumerStrategies;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\InvalidOperationException;
use Prooph\EventStore\Exception\UnexpectedOperationResult;
use Prooph\EventStore\Internal\PersistentSubscriptionCreateResult;
use Prooph\EventStore\Internal\PersistentSubscriptionCreateStatus;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\Messages\ClientMessages\CreatePersistentSubscription;
use Prooph\EventStoreClient\Messages\ClientMessages\CreatePersistentSubscriptionCompleted;
use Prooph\EventStoreClient\Messages\ClientMessages\CreatePersistentSubscriptionCompleted\CreatePersistentSubscriptionResult;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Psr\Log\LoggerInterface as Logger;

/** @internal */
class CreatePersistentSubscriptionOperation extends AbstractOperation
{
    private string $stream;
    private string $groupName;
    private PersistentSubscriptionSettings $settings;

    public function __construct(
        Logger $logger,
        Deferred $deferred,
        string $stream,
        string $groupNameName,
        PersistentSubscriptionSettings $settings,
        ?UserCredentials $userCredentials
    ) {
        $this->stream = $stream;
        $this->groupName = $groupNameName;
        $this->settings = $settings;

        parent::__construct(
            $logger,
            $deferred,
            $userCredentials,
            TcpCommand::createPersistentSubscription(),
            TcpCommand::createPersistentSubscriptionCompleted(),
            CreatePersistentSubscriptionCompleted::class
        );
    }

    protected function createRequestDto(): Message
    {
        $message = new CreatePersistentSubscription();
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
        $message->setPreferRoundRobin($this->settings->namedConsumerStrategy() === SystemConsumerStrategies::ROUND_ROBIN);
        $message->setCheckpointAfterTime($this->settings->checkPointAfterMilliseconds());
        $message->setCheckpointMaxCount($this->settings->maxCheckPointCount());
        $message->setCheckpointMinCount($this->settings->minCheckPointCount());
        $message->setSubscriberMaxCount($this->settings->maxSubscriberCount());
        $message->setNamedConsumerStrategy($this->settings->namedConsumerStrategy());

        return $message;
    }

    protected function inspectResponse(Message $response): InspectionResult
    {
        \assert($response instanceof CreatePersistentSubscriptionCompleted);

        switch ($response->getResult()) {
            case CreatePersistentSubscriptionResult::Success:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'Success');
            case CreatePersistentSubscriptionResult::Fail:
                $this->fail(new InvalidOperationException(\sprintf(
                    'Subscription group \'%s\' on stream \'%s\' failed \'%s\'',
                    $this->groupName,
                    $this->stream,
                    $response->getReason()
                )));

                return new InspectionResult(InspectionDecision::endOperation(), 'Fail');
            case CreatePersistentSubscriptionResult::AccessDenied:
                $this->fail(AccessDenied::toStream($this->stream));

                return new InspectionResult(InspectionDecision::endOperation(), 'AccessDenied');
            case CreatePersistentSubscriptionResult::AlreadyExists:
                $this->fail(new InvalidOperationException(\sprintf(
                    'Subscription group \'%s\' on stream \'%s\' already exists',
                    $this->groupName,
                    $this->stream
                )));

                return new InspectionResult(InspectionDecision::endOperation(), 'AlreadyExists');
            default:
                throw new UnexpectedOperationResult();
        }
    }

    protected function transformResponse(Message $response): PersistentSubscriptionCreateResult
    {
        \assert($response instanceof CreatePersistentSubscriptionCompleted);

        return new PersistentSubscriptionCreateResult(
            PersistentSubscriptionCreateStatus::success()
        );
    }

    public function name(): string
    {
        return 'CreatePersistentSubscription';
    }

    public function __toString(): string
    {
        return \sprintf('Stream: %s, Group Name: %s', $this->stream, $this->groupName);
    }
}
