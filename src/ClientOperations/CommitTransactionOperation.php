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

/** @internal */
class CommitTransactionOperation extends AbstractOperation
{
    private bool $requireMaster;
    private int $transactionId;

    public function __construct(
        Logger $logger,
        Deferred $deferred,
        bool $requireMaster,
        int $transactionId,
        ?UserCredentials $userCredentials
    ) {
        $this->requireMaster = $requireMaster;
        $this->transactionId = $transactionId;

        parent::__construct(
            $logger,
            $deferred,
            $userCredentials,
            TcpCommand::transactionCommit(),
            TcpCommand::transactionCommitCompleted(),
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

    protected function inspectResponse(Message $response): InspectionResult
    {
        \assert($response instanceof TransactionCommitCompleted);

        switch ($response->getResult()) {
            case OperationResult::Success:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'Success');
            case OperationResult::PrepareTimeout:
                return new InspectionResult(InspectionDecision::retry(), 'PrepareTimeout');
            case OperationResult::ForwardTimeout:
                return new InspectionResult(InspectionDecision::retry(), 'ForwardTimeout');
            case OperationResult::CommitTimeout:
                return new InspectionResult(InspectionDecision::retry(), 'CommitTimeout');
            case OperationResult::WrongExpectedVersion:
                $this->fail(new WrongExpectedVersion(\sprintf(
                    'Commit transaction failed due to WrongExpectedVersion. Transaction id: \'%s\'',
                    $this->transactionId
                )));

                return new InspectionResult(InspectionDecision::endOperation(), 'WrongExpectedVersion');
            case OperationResult::StreamDeleted:
                $this->fail(new StreamDeleted());

                return new InspectionResult(InspectionDecision::endOperation(), 'StreamDeleted');
            case OperationResult::InvalidTransaction:
                $this->fail(new InvalidTransaction());

                return new InspectionResult(InspectionDecision::endOperation(), 'InvalidTransaction');
            case OperationResult::AccessDenied:
                $exception = new AccessDenied('Write access denied');
                $this->fail($exception);

                return new InspectionResult(InspectionDecision::endOperation(), 'AccessDenied');
            default:
                throw new UnexpectedOperationResult();
        }
    }

    protected function transformResponse(Message $response): WriteResult
    {
        \assert($response instanceof TransactionCommitCompleted);

        return new WriteResult(
            $response->getLastEventNumber(),
            new Position(
                $response->getCommitPosition() ?? -1,
                $response->getPreparePosition() ?? -1
            )
        );
    }

    public function name(): string
    {
        return 'CommitTransaction';
    }

    public function __toString(): string
    {
        return \sprintf('TransactionId: %s, RequireMaster: %s',
            $this->transactionId,
            $this->requireMaster ? 'yes' : 'no'
        );
    }
}
