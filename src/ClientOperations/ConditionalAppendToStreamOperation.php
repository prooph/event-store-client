<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2019 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\ClientOperations;

use Amp\Deferred;
use Prooph\EventStore\ConditionalWriteResult;
use Prooph\EventStore\ConditionalWriteStatus;
use Prooph\EventStore\EventData;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\InvalidTransaction;
use Prooph\EventStore\Exception\UnexpectedOperationResult;
use Prooph\EventStore\Position;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\Internal\NewEventConverter;
use Prooph\EventStoreClient\Messages\ClientMessages\OperationResult;
use Prooph\EventStoreClient\Messages\ClientMessages\WriteEvents;
use Prooph\EventStoreClient\Messages\ClientMessages\WriteEventsCompleted;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use ProtobufMessage;
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

    protected function createRequestDto(): ProtobufMessage
    {
        $message = new WriteEvents();
        $message->setEventStreamId($this->stream);
        $message->setExpectedVersion($this->expectedVersion);
        $message->setRequireMaster($this->requireMaster);

        foreach ($this->events as $event) {
            $message->appendEvents(NewEventConverter::convert($event));
        }

        return $message;
    }

    protected function inspectResponse(ProtobufMessage $response): InspectionResult
    {
        \assert($response instanceof WriteEventsCompleted);

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

    protected function transformResponse(ProtobufMessage $response): ConditionalWriteResult
    {
        \assert($response instanceof WriteEventsCompleted);

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
        return \sprintf('Stream: %s, ExpectedVersion: %d, RequireMaster: %s',
            $this->stream,
            $this->expectedVersion,
            $this->requireMaster ? 'yes' : 'no'
        );
    }
}
