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

/** @internal */
class DeleteStreamOperation extends AbstractOperation
{
    private bool $requireMaster;
    private string $stream;
    private int $expectedVersion;
    private bool $hardDelete;

    public function __construct(
        Logger $logger,
        Deferred $deferred,
        bool $requireMaster,
        string $stream,
        int $expectedVersion,
        bool $hardDelete,
        ?UserCredentials $userCredentials
    ) {
        $this->requireMaster = $requireMaster;
        $this->stream = $stream;
        $this->expectedVersion = $expectedVersion;
        $this->hardDelete = $hardDelete;

        parent::__construct(
            $logger,
            $deferred,
            $userCredentials,
            TcpCommand::deleteStream(),
            TcpCommand::deleteStreamCompleted(),
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

    public function inspectResponse(Message $response): InspectionResult
    {
        /** @var DeleteStreamCompleted $response */

        switch ($response->getResult()) {
            case OperationResult::Success:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'Success');
            case OperationResult::PrepareTimeout:
                return new InspectionResult(InspectionDecision::retry(), 'PrepareTimeout');
            case OperationResult::CommitTimeout:
                return new InspectionResult(InspectionDecision::retry(), 'CommitTimeout');
            case OperationResult::ForwardTimeout:
                return new InspectionResult(InspectionDecision::retry(), 'ForwardTimeout');
            case OperationResult::WrongExpectedVersion:
                $this->fail(WrongExpectedVersion::with(
                    $this->stream,
                    $this->expectedVersion
                ));

                return new InspectionResult(InspectionDecision::endOperation(), 'WrongExpectedVersion');
            case OperationResult::StreamDeleted:
                $exception = StreamDeleted::with($this->stream);
                $this->fail($exception);

                return new InspectionResult(InspectionDecision::endOperation(), 'StreamDeleted');
            case OperationResult::InvalidTransaction:
                $exception = new InvalidTransaction();
                $this->fail($exception);

                return new InspectionResult(InspectionDecision::endOperation(), 'InvalidTransaction');
            case OperationResult::AccessDenied:
                $exception = AccessDenied::toStream($this->stream);
                $this->fail($exception);

                return new InspectionResult(InspectionDecision::endOperation(), 'AccessDenied');
            default:
                throw new UnexpectedOperationResult();
        }
    }

    protected function transformResponse(Message $response): DeleteResult
    {
        \assert($response instanceof DeleteStreamCompleted);

        return new DeleteResult(new Position(
            $response->getCommitPosition() ?? -1,
            $response->getCommitPosition() ?? -1)
        );
    }

    public function name(): string
    {
        return 'DeleteStream';
    }

    public function __toString(): string
    {
        return \sprintf('Stream: %s, ExpectedVersion: %d, RequireMaster: %s, HardDelete: %s',
            $this->stream,
            $this->expectedVersion,
            $this->requireMaster ? 'yes' : 'no',
            $this->hardDelete ? 'yes' : 'no'
        );
    }
}
