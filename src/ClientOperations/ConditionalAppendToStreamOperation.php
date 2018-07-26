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
use Google\Protobuf\Internal\Message;
use Prooph\EventStoreClient\ConditionalWriteResult;
use Prooph\EventStoreClient\ConditionalWriteStatus;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\Exception\AccessDeniedException;
use Prooph\EventStoreClient\Exception\InvalidTransactionException;
use Prooph\EventStoreClient\Exception\UnexpectedOperationResult;
use Prooph\EventStoreClient\Internal\NewEventConverter;
use Prooph\EventStoreClient\Messages\ClientMessages\OperationResult;
use Prooph\EventStoreClient\Messages\ClientMessages\WriteEvents;
use Prooph\EventStoreClient\Messages\ClientMessages\WriteEventsCompleted;
use Prooph\EventStoreClient\Position;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Prooph\EventStoreClient\UserCredentials;
use Psr\Log\LoggerInterface as Logger;

/** @internal */
class ConditionalAppendToStreamOperation extends AbstractOperation
{
    /** @var bool */
    private $requireMaster;
    /** @var string */
    private $stream;
    /** @var int */
    private $expectedVersion;
    /** @var EventData[] */
    private $events;

    public function __construct(
        Logger $logger,
        Deferred $deferred,
        bool $requireMaster,
        string $stream,
        int $expectedVersion,
        array $events,
        ?UserCredentials $userCredentials
    ) {
        $this->requireMaster = $requireMaster;
        $this->stream = $stream;
        $this->expectedVersion = $expectedVersion;
        $this->events = $events;

        parent::__construct(
            $logger,
            $deferred,
            $userCredentials,
            TcpCommand::writeEvents(),
            TcpCommand::writeEventsCompleted(),
            WriteEventsCompleted::class
        );
    }

    protected function createRequestDto(): Message
    {
        $dtos = [];

        foreach ($this->events as $event) {
            $dtos[] = NewEventConverter::convert($event);
        }

        $message = new WriteEvents();
        $message->setEventStreamId($this->stream);
        $message->setExpectedVersion($this->expectedVersion);
        $message->setEvents($dtos);
        $message->setRequireMaster($this->requireMaster);

        return $message;
    }

    protected function inspectResponse(Message $response): InspectionResult
    {
        /** @var WriteEventsCompleted $response */
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
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'ExpectedVersionMismatch');
            case OperationResult::StreamDeleted:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'StreamDeleted');
            case OperationResult::InvalidTransaction:
                $exception = new InvalidTransactionException();
                $this->fail($exception);

                return new InspectionResult(InspectionDecision::endOperation(), 'InvalidTransaction');
            case OperationResult::AccessDenied:
                $exception = AccessDeniedException::toStream($this->stream);
                $this->fail($exception);

                return new InspectionResult(InspectionDecision::endOperation(), 'AccessDenied');
            default:
                throw new UnexpectedOperationResult();
        }
    }

    protected function transformResponse(Message $response): ConditionalWriteResult
    {
        /** @var WriteEventsCompleted $response */
        if ($response->getResult() === OperationResult::WrongExpectedVersion) {
            return ConditionalWriteResult::fail(ConditionalWriteStatus::versionMismatch());
        }

        if ($response->getResult() === OperationResult::StreamDeleted) {
            return ConditionalWriteResult::fail(ConditionalWriteStatus::streamDeleted());
        }

        return ConditionalWriteResult::success(
            $response->getLastEventNumber(),
            new Position(
                $response->getCommitPosition() ?? -1,
                $response->getPreparePosition() ?? -1
            )
        );
    }

    public function name(): string
    {
        return 'ConditionalAppendToStream';
    }

    public function __toString(): string
    {
        return \sprintf('Stream: %s, ExpectedVersion: %d, RequireMaster:',
            $this->stream,
            $this->expectedVersion,
            $this->requireMaster ? 'yes' : 'no'
        );
    }
}
