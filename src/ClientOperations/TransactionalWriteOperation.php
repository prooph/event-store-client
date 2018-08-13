<?php
/**
 * This file is part of the prooph/event-store-client.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\ClientOperations;

use Amp\Deferred;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\Exception\AccessDeniedException;
use Prooph\EventStoreClient\Exception\UnexpectedOperationResult;
use Prooph\EventStoreClient\Internal\NewEventConverter;
use Prooph\EventStoreClient\Messages\ClientMessages\OperationResult;
use Prooph\EventStoreClient\Messages\ClientMessages\TransactionWrite;
use Prooph\EventStoreClient\Messages\ClientMessages\TransactionWriteCompleted;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Prooph\EventStoreClient\UserCredentials;
use ProtobufMessage;
use Psr\Log\LoggerInterface as Logger;

/** @internal */
class TransactionalWriteOperation extends AbstractOperation
{
    /** @var bool */
    private $requireMaster;
    /** @var int */
    private $transactionId;
    /** @var EventData[] */
    private $events;

    public function __construct(
        Logger $logger,
        Deferred $deferred,
        bool $requireMaster,
        int $transactionId,
        array $events,
        ?UserCredentials $userCredentials
    ) {
        $this->requireMaster = $requireMaster;
        $this->transactionId = $transactionId;
        $this->events = $events;

        parent::__construct(
            $logger,
            $deferred,
            $userCredentials,
            TcpCommand::transactionWrite(),
            TcpCommand::transactionWriteCompleted(),
            TransactionWriteCompleted::class
        );
    }

    protected function createRequestDto(): ProtobufMessage
    {
        $message = new TransactionWrite();
        $message->setRequireMaster($this->requireMaster);
        $message->setTransactionId($this->transactionId);

        foreach ($this->events as $event) {
            $message->appendEvents(NewEventConverter::convert($event));
        }

        return $message;
    }

    protected function inspectResponse(ProtobufMessage $response): InspectionResult
    {
        /** @var TransactionWriteCompleted $response */
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
            case OperationResult::AccessDenied:
                $exception = new AccessDeniedException('Write access denied');
                $this->fail($exception);

                return new InspectionResult(InspectionDecision::endOperation(), 'AccessDenied');
            default:
                throw new UnexpectedOperationResult();
        }
    }

    protected function transformResponse(ProtobufMessage $response): void
    {
    }

    public function name(): string
    {
        return 'TransactionalWrite';
    }

    public function __toString(): string
    {
        return \sprintf('TransactionId: %s, RequireMaster: %s',
            $this->transactionId,
            $this->requireMaster ? 'yes' : 'no'
        );
    }
}
