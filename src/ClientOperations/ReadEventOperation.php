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
use Prooph\EventStoreClient\EventReadResult;
use Prooph\EventStoreClient\EventReadStatus;
use Prooph\EventStoreClient\Exception\AccessDeniedException;
use Prooph\EventStoreClient\Exception\ServerError;
use Prooph\EventStoreClient\Internal\EventMessageConverter;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadEvent;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadEventCompleted;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadEventCompleted\ReadEventResult;
use Prooph\EventStoreClient\ResolvedEvent;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Prooph\EventStoreClient\UserCredentials;
use Psr\Log\LoggerInterface as Logger;

/** @internal */
class ReadEventOperation extends AbstractOperation
{
    /** @var bool */
    private $requireMaster;
    /** @var string */
    private $stream;
    /** @var int */
    private $eventNumber;
    /** @var bool */
    private $resolveLinkTos;

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
                $this->fail(AccessDeniedException::toStream($this->stream));

                return new InspectionResult(InspectionDecision::endOperation(), 'AccessDenied');
            default:
                throw new ServerError('Unexpected ReadEventResult');
        }
    }

    protected function transformResponse(Message $response): EventReadResult
    {
        /* @var ReadEventCompleted $response */
        $eventMessage = $response->getEvent();
        $event = null;
        $link = null;

        if ($eventMessage->getEvent()) {
            $event = EventMessageConverter::convertEventRecordMessageToEventRecord($eventMessage->getEvent());
        }

        if ($link = $eventMessage->getLink()) {
            $link = EventMessageConverter::convertEventRecordMessageToEventRecord($link);
        }

        if ($event) {
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
