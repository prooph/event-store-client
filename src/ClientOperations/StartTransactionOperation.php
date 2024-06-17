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
use Prooph\EventStore\EventStoreTransaction;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\InvalidTransaction;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\UnexpectedOperationResult;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\Internal\EventStoreTransactionConnection;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\Messages\ClientMessages\OperationResult;
use Prooph\EventStoreClient\Messages\ClientMessages\TransactionStart;
use Prooph\EventStoreClient\Messages\ClientMessages\TransactionStartCompleted;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Psr\Log\LoggerInterface as Logger;

/**
 * @internal
 * @extends AbstractOperation<TransactionStartCompleted, EventStoreTransaction>
 */
class StartTransactionOperation extends AbstractOperation
{
    public function __construct(
        Logger $logger,
        DeferredFuture $deferred,
        private readonly bool $requireMaster,
        private readonly string $stream,
        private readonly int $expectedVersion,
        private readonly EventStoreTransactionConnection $parentConnection,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct(
            $logger,
            $deferred,
            $userCredentials,
            TcpCommand::TransactionStart,
            TcpCommand::TransactionStartCompleted,
            TransactionStartCompleted::class
        );
    }

    protected function createRequestDto(): Message
    {
        $message = new TransactionStart();
        $message->setRequireMaster($this->requireMaster);
        $message->setEventStreamId($this->stream);
        $message->setExpectedVersion($this->expectedVersion);

        return $message;
    }

    /**
     * @param TransactionStartCompleted $response
     * @return InspectionResult
     */
    protected function inspectResponse(Message $response): InspectionResult
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
                $this->fail(StreamDeleted::with($this->stream));

                return new InspectionResult(InspectionDecision::EndOperation, 'StreamDeleted');
            case OperationResult::InvalidTransaction:
                $this->fail(new InvalidTransaction());

                return new InspectionResult(InspectionDecision::EndOperation, 'InvalidTransaction');
            case OperationResult::AccessDenied:
                $this->fail(AccessDenied::toStream($this->stream));

                return new InspectionResult(InspectionDecision::EndOperation, 'AccessDenied');
            default:
                throw new UnexpectedOperationResult();
        }
    }

    protected function transformResponse(Message $response): EventStoreTransaction
    {
        return new EventStoreTransaction(
            (int) $response->getTransactionId(),
            $this->credentials,
            $this->parentConnection
        );
    }

    public function name(): string
    {
        return 'StartTransaction';
    }

    public function __toString(): string
    {
        return \sprintf(
            'Stream: %s, ExpectedVersion: %d, RequireMaster: %s',
            $this->stream,
            $this->expectedVersion,
            $this->requireMaster ? 'yes' : 'no'
        );
    }
}
