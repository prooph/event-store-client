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
use Prooph\EventStore\EventData;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\UnexpectedOperationResult;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\Internal\NewEventConverter;
use Prooph\EventStoreClient\Messages\ClientMessages\NewEvent;
use Prooph\EventStoreClient\Messages\ClientMessages\OperationResult;
use Prooph\EventStoreClient\Messages\ClientMessages\TransactionWrite;
use Prooph\EventStoreClient\Messages\ClientMessages\TransactionWriteCompleted;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Psr\Log\LoggerInterface as Logger;

/** @internal */
class TransactionalWriteOperation extends AbstractOperation
{
    private bool $requireMaster;
    private int $transactionId;
    /** @var EventData[] */
    private array $events;

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

    protected function createRequestDto(): Message
    {
        $events = \array_map(
            fn (EventData $event): NewEvent => NewEventConverter::convert($event),
            $this->events
        );

        $message = new TransactionWrite();
        $message->setRequireMaster($this->requireMaster);
        $message->setTransactionId($this->transactionId);
        $message->setEvents($events);

        return $message;
    }

    protected function inspectResponse(Message $response): InspectionResult
    {
        \assert($response instanceof TransactionWriteCompleted);

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
                $exception = new AccessDenied('Write access denied');
                $this->fail($exception);

                return new InspectionResult(InspectionDecision::endOperation(), 'AccessDenied');
            default:
                throw new UnexpectedOperationResult();
        }
    }

    protected function transformResponse(Message $response): void
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
