<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2024 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2024 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\ClientOperations;

use Amp\DeferredFuture;
use Google\Protobuf\Internal\Message;
use Prooph\EventStore\DeleteResult;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\InvalidTransaction;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\UnexpectedOperationResult;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\Position;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\Messages\ClientMessages\DeleteStream;
use Prooph\EventStoreClient\Messages\ClientMessages\DeleteStreamCompleted;
use Prooph\EventStoreClient\Messages\ClientMessages\OperationResult;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Psr\Log\LoggerInterface as Logger;

/**
 * @internal
 * @extends AbstractOperation<DeleteStreamCompleted, DeleteResult>
 */
class DeleteStreamOperation extends AbstractOperation
{
    public function __construct(
        Logger $logger,
        DeferredFuture $deferred,
        private readonly bool $requireMaster,
        private readonly string $stream,
        private readonly int $expectedVersion,
        private readonly bool $hardDelete,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct(
            $logger,
            $deferred,
            $userCredentials,
            TcpCommand::DeleteStream,
            TcpCommand::DeleteStreamCompleted,
            DeleteStreamCompleted::class
        );
    }

    protected function createRequestDto(): Message
    {
        $message = new DeleteStream();
        $message->setEventStreamId($this->stream);
        $message->setExpectedVersion($this->expectedVersion);
        $message->setHardDelete($this->hardDelete);
        $message->setRequireMaster($this->requireMaster);

        return $message;
    }

    /**
     * @param DeleteStreamCompleted $response
     * @return InspectionResult
     */
    public function inspectResponse(Message $response): InspectionResult
    {
        switch ($response->getResult()) {
            case OperationResult::Success:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::EndOperation, 'Success');
            case OperationResult::PrepareTimeout:
                return new InspectionResult(InspectionDecision::Retry, 'PrepareTimeout');
            case OperationResult::CommitTimeout:
                return new InspectionResult(InspectionDecision::Retry, 'CommitTimeout');
            case OperationResult::ForwardTimeout:
                return new InspectionResult(InspectionDecision::Retry, 'ForwardTimeout');
            case OperationResult::WrongExpectedVersion:
                $this->fail(WrongExpectedVersion::with(
                    $this->stream,
                    $this->expectedVersion
                ));

                return new InspectionResult(InspectionDecision::EndOperation, 'WrongExpectedVersion');
            case OperationResult::StreamDeleted:
                $exception = StreamDeleted::with($this->stream);
                $this->fail($exception);

                return new InspectionResult(InspectionDecision::EndOperation, 'StreamDeleted');
            case OperationResult::InvalidTransaction:
                $exception = new InvalidTransaction();
                $this->fail($exception);

                return new InspectionResult(InspectionDecision::EndOperation, 'InvalidTransaction');
            case OperationResult::AccessDenied:
                $exception = AccessDenied::toStream($this->stream);
                $this->fail($exception);

                return new InspectionResult(InspectionDecision::EndOperation, 'AccessDenied');
            default:
                throw new UnexpectedOperationResult();
        }
    }

    /**
     * @param DeleteStreamCompleted $response
     * @return DeleteResult
     */
    protected function transformResponse(Message $response): DeleteResult
    {
        /** @psalm-suppress DocblockTypeContradiction */
        return new DeleteResult(
            new Position(
                (int) ($response->getCommitPosition() ?? -1),
                (int) ($response->getCommitPosition() ?? -1)
            )
        );
    }

    public function name(): string
    {
        return 'DeleteStream';
    }

    public function __toString(): string
    {
        return \sprintf(
            'Stream: %s, ExpectedVersion: %d, RequireMaster: %s, HardDelete: %s',
            $this->stream,
            $this->expectedVersion,
            $this->requireMaster ? 'yes' : 'no',
            $this->hardDelete ? 'yes' : 'no'
        );
    }
}
