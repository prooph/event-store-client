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

/** @internal */
class ReadEventOperation extends AbstractOperation
{
    private bool $requireMaster;
    private string $stream;
    private int $eventNumber;
    private bool $resolveLinkTos;

    public function __construct(
        Logger $logger,
        Deferred $deferred,
        bool $requireMaster,
        string $stream,
        int $eventNumber,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials
    ) {
        $this->requireMaster = $requireMaster;
        $this->stream = $stream;
        $this->eventNumber = $eventNumber;
        $this->resolveLinkTos = $resolveLinkTos;

        parent::__construct(
            $logger,
            $deferred,
            $userCredentials,
            TcpCommand::readEvent(),
            TcpCommand::readEventCompleted(),
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

    protected function inspectResponse(Message $response): InspectionResult
    {
        /** @var ReadEventCompleted $response */

        switch ($response->getResult()) {
            case ReadEventResult::Success:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'Success');
            case ReadEventResult::NotFound:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'NotFound');
            case ReadEventResult::NoStream:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'NoStream');
            case ReadEventResult::StreamDeleted:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'StreamDeleted');
            case ReadEventResult::Error:
                $this->fail(new ServerError($response->getError()));

                return new InspectionResult(InspectionDecision::endOperation(), 'Error');
            case ReadEventResult::AccessDenied:
                $this->fail(AccessDenied::toStream($this->stream));

                return new InspectionResult(InspectionDecision::endOperation(), 'AccessDenied');
            default:
                throw new ServerError('Unexpected ReadEventResult');
        }
    }

    protected function transformResponse(Message $response): EventReadResult
    {
        /* @var ReadEventCompleted $response */
        $eventMessage = $response->getEvent();

        if ($event = $eventMessage->getEvent()) {
            $event = EventMessageConverter::convertEventRecordMessageToEventRecord($eventMessage->getEvent());
        }

        if ($link = $eventMessage->getLink()) {
            $link = EventMessageConverter::convertEventRecordMessageToEventRecord($link);
        }

        if (EventReadStatus::SUCCESS === $response->getResult()) {
            $resolvedEvent = new ResolvedEvent($event, $link, null);
        } else {
            $resolvedEvent = null;
        }

        return new EventReadResult(
            EventReadStatus::byValue($response->getResult()),
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
        return \sprintf('Stream: %s, EventNumber: %d, ResolveLinkTo: %s, RequireMaster: %s',
            $this->stream,
            $this->eventNumber,
            $this->resolveLinkTos ? 'yes' : 'no',
            $this->requireMaster ? 'yes' : 'no'
        );
    }
}
