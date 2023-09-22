<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2022 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2022 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\ClientOperations;

use Amp\DeferredFuture;
use Google\Protobuf\Internal\Message;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\ServerError;
use Prooph\EventStore\ReadDirection;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\Internal\EventMessageConverter;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadStreamEvents;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadStreamEventsCompleted;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadStreamEventsCompleted\ReadStreamResult;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Psr\Log\LoggerInterface as Logger;

/**
 * @internal
 * @extends AbstractOperation<ReadStreamEventsCompleted, StreamEventsSlice>
 */
class ReadStreamEventsBackwardOperation extends AbstractOperation
{
    public function __construct(
        Logger $logger,
        DeferredFuture $deferred,
        private readonly bool $requireMaster,
        private readonly string $stream,
        private readonly int $fromEventNumber,
        private readonly int $maxCount,
        private readonly bool $resolveLinkTos,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct(
            $logger,
            $deferred,
            $userCredentials,
            TcpCommand::ReadStreamEventsBackward,
            TcpCommand::ReadStreamEventsBackwardCompleted,
            ReadStreamEventsCompleted::class
        );
    }

    protected function createRequestDto(): Message
    {
        $message = new ReadStreamEvents();
        $message->setRequireMaster($this->requireMaster);
        $message->setEventStreamId($this->stream);
        $message->setFromEventNumber($this->fromEventNumber);
        $message->setMaxCount($this->maxCount);
        $message->setResolveLinkTos($this->resolveLinkTos);

        return $message;
    }

    /**
     * @param ReadStreamEventsCompleted $response
     * @return InspectionResult
     */
    protected function inspectResponse(Message $response): InspectionResult
    {
        switch ($response->getResult()) {
            case ReadStreamResult::Success:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::EndOperation, 'Success');
            case ReadStreamResult::StreamDeleted:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::EndOperation, 'StreamDeleted');
            case ReadStreamResult::NoStream:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::EndOperation, 'NoStream');
            case ReadStreamResult::Error:
                $this->fail(new ServerError($response->getError()));

                return new InspectionResult(InspectionDecision::EndOperation, 'Error');
            case ReadStreamResult::AccessDenied:
                $this->fail(AccessDenied::toStream($this->stream));

                return new InspectionResult(InspectionDecision::EndOperation, 'AccessDenied');
            default:
                throw new ServerError('Unexpected ReadStreamResult');
        }
    }

    /**
     * @param ReadStreamEventsCompleted $response
     * @return StreamEventsSlice
     */
    protected function transformResponse(Message $response): StreamEventsSlice
    {
        $resolvedEvents = [];

        foreach ($response->getEvents() as $record) {
            $resolvedEvents[] = new ResolvedEvent(
                EventMessageConverter::convertEventRecordMessageToEventRecord($record->getEvent()),
                EventMessageConverter::convertEventRecordMessageToEventRecord($record->getLink()),
                null
            );
        }

        return new StreamEventsSlice(
            SliceReadStatus::from($response->getResult()),
            $this->stream,
            $this->fromEventNumber,
            ReadDirection::Backward,
            $resolvedEvents,
            (int) $response->getNextEventNumber(),
            (int) $response->getLastEventNumber(),
            $response->getIsEndOfStream()
        );
    }

    public function name(): string
    {
        return 'ReadStreamEventsBackward';
    }

    public function __toString(): string
    {
        return \sprintf(
            'Stream: %s, FromEventNumber: %d, MaxCount: %d, ResolveLinkTos: %s, RequireMaster: %s',
            $this->stream,
            $this->fromEventNumber,
            $this->maxCount,
            $this->resolveLinkTos ? 'yes' : 'no',
            $this->requireMaster ? 'yes' : 'no'
        );
    }
}
