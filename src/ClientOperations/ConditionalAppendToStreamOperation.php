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
use Prooph\EventStore\ConditionalWriteResult;
use Prooph\EventStore\ConditionalWriteStatus;
use Prooph\EventStore\EventData;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\InvalidTransaction;
use Prooph\EventStore\Exception\UnexpectedOperationResult;
use Prooph\EventStore\Position;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\Internal\NewEventConverter;
use Prooph\EventStoreClient\Messages\ClientMessages\NewEvent;
use Prooph\EventStoreClient\Messages\ClientMessages\OperationResult;
use Prooph\EventStoreClient\Messages\ClientMessages\WriteEvents;
use Prooph\EventStoreClient\Messages\ClientMessages\WriteEventsCompleted;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Psr\Log\LoggerInterface as Logger;

/**
 * @internal
 * @extends AbstractOperation<WriteEventsCompleted, ConditionalWriteResult>
 */
class ConditionalAppendToStreamOperation extends AbstractOperation
{
    /**
     * @param Logger $logger
     * @param DeferredFuture $deferred
     * @param bool $requireMaster
     * @param string $stream
     * @param int $expectedVersion
     * @param list<EventData> $events
     * @param UserCredentials|null $userCredentials
     */
    public function __construct(
        Logger $logger,
        DeferredFuture $deferred,
        private readonly bool $requireMaster,
        private readonly string $stream,
        private readonly int $expectedVersion,
        private readonly array $events,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct(
            $logger,
            $deferred,
            $userCredentials,
            TcpCommand::WriteEvents,
            TcpCommand::WriteEventsCompleted,
            WriteEventsCompleted::class
        );
    }

    protected function createRequestDto(): Message
    {
        $events = \array_map(
            fn (EventData $event): NewEvent => NewEventConverter::convert($event),
            $this->events
        );

        $message = new WriteEvents();
        $message->setEventStreamId($this->stream);
        $message->setExpectedVersion($this->expectedVersion);
        $message->setRequireMaster($this->requireMaster);
        $message->setEvents($events);

        return $message;
    }

    /**
     * @param WriteEventsCompleted $response
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
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::EndOperation, 'ExpectedVersionMismatch');
            case OperationResult::StreamDeleted:
                $this->succeed($response);

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

    protected function transformResponse(Message $response): ConditionalWriteResult
    {
        if ($response->getResult() === OperationResult::WrongExpectedVersion) {
            return ConditionalWriteResult::fail(ConditionalWriteStatus::VersionMismatch);
        }

        if ($response->getResult() === OperationResult::StreamDeleted) {
            return ConditionalWriteResult::fail(ConditionalWriteStatus::StreamDeleted);
        }

        /** @psalm-suppress DocblockTypeContradiction */
        return ConditionalWriteResult::success(
            (int) $response->getLastEventNumber(),
            new Position(
                (int) ($response->getCommitPosition() ?? -1),
                (int) ($response->getPreparePosition() ?? -1)
            )
        );
    }

    public function name(): string
    {
        return 'ConditionalAppendToStream';
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
