<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\ClientOperations;

use Amp\Deferred;
use Google\Protobuf\Internal\Message;
use Prooph\EventStoreClient\Exception\AccessDeniedException;
use Prooph\EventStoreClient\Exception\InvalidOperationException;
use Prooph\EventStoreClient\Exception\UnexpectedOperationResult;
use Prooph\EventStoreClient\Internal\PersistentSubscriptionDeleteResult;
use Prooph\EventStoreClient\Internal\PersistentSubscriptionDeleteStatus;
use Prooph\EventStoreClient\Messages\ClientMessages\DeletePersistentSubscription;
use Prooph\EventStoreClient\Messages\ClientMessages\DeletePersistentSubscriptionCompleted;
use Prooph\EventStoreClient\Messages\ClientMessages\DeletePersistentSubscriptionCompleted\DeletePersistentSubscriptionResult;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Prooph\EventStoreClient\UserCredentials;
use Psr\Log\LoggerInterface as Logger;

/** @internal */
class DeletePersistentSubscriptionOperation extends AbstractOperation
{
    /** @var string */
    private $stream;
    /** @var string */
    private $groupName;

    public function __construct(
        Logger $logger,
        Deferred $deferred,
        string $stream,
        string $groupName,
        ?UserCredentials $userCredentials
    ) {
        $this->stream = $stream;
        $this->groupName = $groupName;

        parent::__construct(
            $logger,
            $deferred,
            $userCredentials,
            TcpCommand::deletePersistentSubscription(),
            TcpCommand::deletePersistentSubscriptionCompleted(),
            DeletePersistentSubscriptionCompleted::class
        );
    }

    protected function createRequestDto(): Message
    {
        $message = new DeletePersistentSubscription();
        $message->setEventStreamId($this->stream);
        $message->setSubscriptionGroupName($this->groupName);

        return $message;
    }

    protected function inspectResponse(Message $response): InspectionResult
    {
        /** @var DeletePersistentSubscriptionCompleted $response */
        switch ($response->getResult()) {
            case DeletePersistentSubscriptionResult::Success:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'Success');
            case DeletePersistentSubscriptionResult::Fail:
                $this->fail(new InvalidOperationException(\sprintf(
                    'Subscription group \'%s\' on stream \'%s\' failed \'%s\'',
                    $this->groupName,
                    $this->stream,
                    $response->getReason()
                )));

                return new InspectionResult(InspectionDecision::endOperation(), 'Fail');
            case DeletePersistentSubscriptionResult::AccessDenied:
                $this->fail(AccessDeniedException::toStream($this->stream));

                return new InspectionResult(InspectionDecision::endOperation(), 'AccessDenied');
            case DeletePersistentSubscriptionResult::DoesNotExist:
                $this->fail(new InvalidOperationException(\sprintf(
                    'Subscription group \'%s\' on stream \'%s\' does not exist',
                    $this->groupName,
                    $this->stream
                )));

                return new InspectionResult(InspectionDecision::endOperation(), 'DoesNotExist');
            default:
                throw new UnexpectedOperationResult();
        }
    }

    protected function transformResponse(Message $response)
    {
        /** @var DeletePersistentSubscriptionCompleted $response */
        if (0 === $response->getResult()) {
            $status = PersistentSubscriptionDeleteStatus::success();
        } else {
            $status = PersistentSubscriptionDeleteStatus::failure();
        }

        return new PersistentSubscriptionDeleteResult($status);
    }

    public function name(): string
    {
        return 'DeletePersistentSubscription';
    }

    public function __toString(): string
    {
        return \sprintf('Stream: %s, Group Name: %s', $this->stream, $this->groupName);
    }
}
