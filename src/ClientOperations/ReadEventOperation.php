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
use Prooph\EventStore\EventReadResult;
use Prooph\EventStore\EventReadStatus;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\ServerError;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\Internal\EventMessageConverter;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadEvent;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadEventCompleted;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadEventCompleted\ReadEventResult;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Psr\Log\LoggerInterface as Logger;

/**
 * @internal
 * @extends AbstractOperation<ReadEventCompleted, EventReadResult>
 */
class ReadEventOperation extends AbstractOperation
{
    public function __construct(
        Logger $logger,
        DeferredFuture $deferred,
        private readonly bool $requireMaster,
        private readonly string $stream,
        private readonly int $eventNumber,
        private readonly bool $resolveLinkTos,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct(
            $logger,
            $deferred,
            $userCredentials,
            TcpCommand::ReadEvent,
            TcpCommand::ReadEventCompleted,
            ReadEventCompleted::class
        );
    }

    protected function createRequestDto(): Message
    {
        $message = new ReadEvent();
        $message->setEventStreamId($this->stream);
        $message->setEventNumber($this->eventNumber);
        $message->setResolveLinkTos($this->resolveLinkTos);
        $message->setRequireMaster($this->requireMaster);

        return $message;
    }

    /**
     * @param ReadEventCompleted $response
     * @return InspectionResult
     */
    protected function inspectResponse(Message $response): InspectionResult
    {
        switch ($response->getResult()) {
            case ReadEventResult::Success:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::EndOperation, 'Success');
            case ReadEventResult::NotFound:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::EndOperation, 'NotFound');
            case ReadEventResult::NoStream:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::EndOperation, 'NoStream');
            case ReadEventResult::StreamDeleted:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::EndOperation, 'StreamDeleted');
            case ReadEventResult::Error:
                $this->fail(new ServerError($response->getError()));

                return new InspectionResult(InspectionDecision::EndOperation, 'Error');
            case ReadEventResult::AccessDenied:
                $this->fail(AccessDenied::toStream($this->stream));

                return new InspectionResult(InspectionDecision::EndOperation, 'AccessDenied');
            default:
                throw new ServerError('Unexpected ReadEventResult');
        }
    }

    /**
     * @param ReadEventCompleted $response
     * @return EventReadResult
     */
    protected function transformResponse(Message $response): EventReadResult
    {
        $eventMessage = $response->getEvent();

        if (EventReadStatus::Success->value === $response->getResult()) {
            /** @psalm-suppress PossiblyInvalidArgument */
            $resolvedEvent = new ResolvedEvent(
                EventMessageConverter::convertEventRecordMessageToEventRecord($eventMessage->getEvent()),
                EventMessageConverter::convertEventRecordMessageToEventRecord($eventMessage->getLink()),
                null
            );
        } else {
            $resolvedEvent = null;
        }

        return new EventReadResult(
            EventReadStatus::from($response->getResult()),
            $this->stream,
            $this->eventNumber,
            $resolvedEvent
        );
    }

    public function name(): string
    {
        return 'ReadEvent';
    }

    public function __toString(): string
    {
        return \sprintf(
            'Stream: %s, EventNumber: %d, ResolveLinkTo: %s, RequireMaster: %s',
            $this->stream,
            $this->eventNumber,
            $this->resolveLinkTos ? 'yes' : 'no',
            $this->requireMaster ? 'yes' : 'no'
        );
    }
}
