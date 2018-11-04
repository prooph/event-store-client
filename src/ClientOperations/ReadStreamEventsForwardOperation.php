<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\ClientOperations;

use Amp\Deferred;
use Prooph\EventStoreClient\Exception\AccessDeniedException;
use Prooph\EventStoreClient\Exception\ServerError;
use Prooph\EventStoreClient\Internal\EventMessageConverter;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadStreamEvents;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadStreamEventsCompleted;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadStreamEventsCompleted_ReadStreamResult as ReadStreamResult;
use Prooph\EventStoreClient\Messages\ClientMessages\ResolvedIndexedEvent;
use Prooph\EventStoreClient\ReadDirection;
use Prooph\EventStoreClient\ResolvedEvent;
use Prooph\EventStoreClient\SliceReadStatus;
use Prooph\EventStoreClient\StreamEventsSlice;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Prooph\EventStoreClient\UserCredentials;
use ProtobufMessage;
use Psr\Log\LoggerInterface as Logger;

/** @internal */
class ReadStreamEventsForwardOperation extends AbstractOperation
{
    /** @var bool */
    private $requireMaster;
    /** @var string */
    private $stream;
    /** @var int */
    private $fromEventNumber;
    /** @var int */
    private $maxCount;
    /** @var bool */
    private $resolveLinkTos;

    public function __construct(
        Logger $logger,
        Deferred $deferred,
        bool $requireMaster,
        string $stream,
        int $fromEventNumber,
        int $maxCount,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials
    ) {
        $this->requireMaster = $requireMaster;
        $this->stream = $stream;
        $this->fromEventNumber = $fromEventNumber;
        $this->maxCount = $maxCount;
        $this->resolveLinkTos = $resolveLinkTos;

        parent::__construct(
            $logger,
            $deferred,
            $userCredentials,
            TcpCommand::readStreamEventsForward(),
            TcpCommand::readStreamEventsForwardCompleted(),
            ReadStreamEventsCompleted::class
        );
    }

    protected function createRequestDto(): ProtobufMessage
    {
        $message = new ReadStreamEvents();
        $message->setRequireMaster($this->requireMaster);
        $message->setEventStreamId($this->stream);
        $message->setFromEventNumber($this->fromEventNumber);
        $message->setMaxCount($this->maxCount);
        $message->setResolveLinkTos($this->resolveLinkTos);

        return $message;
    }

    protected function inspectResponse(ProtobufMessage $response): InspectionResult
    {
        \assert($response instanceof ReadStreamEventsCompleted);

        switch ($response->getResult()) {
            case ReadStreamResult::Success:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'Success');
            case ReadStreamResult::StreamDeleted:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'StreamDeleted');
            case ReadStreamResult::NoStream:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'NoStream');
            case ReadStreamResult::Error:
                $this->fail(new ServerError($response->getError()));

                return new InspectionResult(InspectionDecision::endOperation(), 'Error');
            case ReadStreamResult::AccessDenied:
                $this->fail(AccessDeniedException::toStream($this->stream));

                return new InspectionResult(InspectionDecision::endOperation(), 'AccessDenied');
            default:
                throw new ServerError('Unexpected ReadStreamResult');
        }
    }

    protected function transformResponse(ProtobufMessage $response): StreamEventsSlice
    {
        /* @var ReadStreamEventsCompleted $response */
        $records = $response->getEvents();

        $resolvedEvents = [];

        foreach ($records as $record) {
            \assert($record instanceof ResolvedIndexedEvent);

            if ($event = $record->getEvent()) {
                $event = EventMessageConverter::convertEventRecordMessageToEventRecord($record->getEvent());
            }

            if ($link = $record->getLink()) {
                $link = EventMessageConverter::convertEventRecordMessageToEventRecord($link);
            }

            $resolvedEvents[] = new ResolvedEvent($event, $link, null);
        }

        return new StreamEventsSlice(
            SliceReadStatus::byValue($response->getResult()),
            $this->stream,
            $this->fromEventNumber,
            ReadDirection::forward(),
            $resolvedEvents,
            $response->getNextEventNumber(),
            $response->getLastEventNumber(),
            $response->getIsEndOfStream()
        );
    }

    public function name(): string
    {
        return 'ReadStreamEventsForward';
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
