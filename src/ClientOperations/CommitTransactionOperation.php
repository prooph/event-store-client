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
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\InvalidTransaction;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\UnexpectedOperationResult;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\Position;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\WriteResult;
use Prooph\EventStoreClient\Messages\ClientMessages\OperationResult;
use Prooph\EventStoreClient\Messages\ClientMessages\TransactionCommit;
use Prooph\EventStoreClient\Messages\ClientMessages\TransactionCommitCompleted;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Psr\Log\LoggerInterface as Logger;

/**
 * @internal
 * @extends AbstractOperation<TransactionCommitCompleted, WriteResult>
 */
class CommitTransactionOperation extends AbstractOperation
{
    public function __construct(
        Logger $logger,
        DeferredFuture $deferred,
        private readonly bool $requireMaster,
        private readonly int $transactionId,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct(
            $logger,
            $deferred,
            $userCredentials,
            TcpCommand::TransactionCommit,
            TcpCommand::TransactionCommitCompleted,
            TransactionCommitCompleted::class
        );
    }

    protected function createRequestDto(): Message
    {
        $message = new TransactionCommit();
        $message->setRequireMaster($this->requireMaster);
        $message->setTransactionId($this->transactionId);

        return $message;
    }

    /**
     * @param TransactionCommitCompleted $response
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
            case OperationResult::ForwardTimeout:
                return new InspectionResult(InspectionDecision::Retry, 'ForwardTimeout');
            case OperationResult::CommitTimeout:
                return new InspectionResult(InspectionDecision::Retry, 'CommitTimeout');
            case OperationResult::WrongExpectedVersion:
                $this->fail(new WrongExpectedVersion(\sprintf(
                    'Commit transaction failed due to WrongExpectedVersion. Transaction id: \'%s\'',
                    $this->transactionId
                )));

                return new InspectionResult(InspectionDecision::EndOperation, 'WrongExpectedVersion');
            case OperationResult::StreamDeleted:
                $this->fail(new StreamDeleted());

                return new InspectionResult(InspectionDecision::EndOperation, 'StreamDeleted');
            case OperationResult::InvalidTransaction:
                $this->fail(new InvalidTransaction());

                return new InspectionResult(InspectionDecision::EndOperation, 'InvalidTransaction');
            case OperationResult::AccessDenied:
                $exception = new AccessDenied('Write access denied');
                $this->fail($exception);

                return new InspectionResult(InspectionDecision::EndOperation, 'AccessDenied');
            default:
                throw new UnexpectedOperationResult();
        }
    }

    /**
     * @param TransactionCommitCompleted $response
     * @return WriteResult
     */
    protected function transformResponse(Message $response): WriteResult
    {
        /** @psalm-suppress DocblockTypeContradiction */
        return new WriteResult(
            (int) $response->getLastEventNumber(),
            new Position(
                (int) ($response->getCommitPosition() ?? -1),
                (int) ($response->getPreparePosition() ?? -1)
            )
        );
    }

    public function name(): string
    {
        return 'CommitTransaction';
    }

    public function __toString(): string
    {
        return \sprintf(
            'TransactionId: %s, RequireMaster: %s',
            $this->transactionId,
            $this->requireMaster ? 'yes' : 'no'
        );
    }
}
