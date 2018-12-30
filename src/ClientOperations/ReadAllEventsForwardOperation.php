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
use Prooph\EventStore\AllEventsSlice;
use Prooph\EventStore\Exception\AccessDeniedException;
use Prooph\EventStore\Exception\ServerError;
use Prooph\EventStore\Position;
use Prooph\EventStore\ReadDirection;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\Internal\EventMessageConverter;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadAllEvents;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadAllEventsCompleted;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadAllEventsCompleted_ReadAllResult as ReadAllResult;
use Prooph\EventStoreClient\Messages\ClientMessages\ResolvedEvent as ResolvedEventMessage;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use ProtobufMessage;
use Psr\Log\LoggerInterface as Logger;

/** @internal */
class ReadAllEventsForwardOperation extends AbstractOperation
{
    /** @var bool */
    private $requireMaster;
    /** @var Position */
    private $position;
    /** @var int */
    private $maxCount;
    /** @var bool */
    private $resolveLinkTos;

    public function __construct(
        Logger $logger,
        Deferred $deferred,
        bool $requireMaster,
        Position $position,
        int $maxCount,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials
    ) {
        $this->requireMaster = $requireMaster;
        $this->position = $position;
        $this->maxCount = $maxCount;
        $this->resolveLinkTos = $resolveLinkTos;

        parent::__construct(
            $logger,
            $deferred,
            $userCredentials,
            TcpCommand::readAllEventsForward(),
            TcpCommand::readAllEventsForwardCompleted(),
            ReadAllEventsCompleted::class
        );
    }

    protected function createRequestDto(): ProtobufMessage
    {
        $message = new ReadAllEvents();
        $message->setRequireMaster($this->requireMaster);
        $message->setCommitPosition($this->position->commitPosition());
        $message->setPreparePosition($this->position->preparePosition());
        $message->setMaxCount($this->maxCount);
        $message->setResolveLinkTos($this->resolveLinkTos);

        return $message;
    }

    protected function inspectResponse(ProtobufMessage $response): InspectionResult
    {
        \assert($response instanceof ReadAllEventsCompleted);

        switch ($response->getResult()) {
            case ReadAllResult::Success:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'Success');
            case ReadAllResult::Error:
                $this->fail(new ServerError($response->getError()));

                return new InspectionResult(InspectionDecision::endOperation(), 'Error');
            case ReadAllResult::AccessDenied:
                $this->fail(AccessDeniedException::toAllStream());

                return new InspectionResult(InspectionDecision::endOperation(), 'AccessDenied');
            default:
                throw new ServerError('Unexpected ReadAllResult');
        }
    }

    protected function transformResponse(ProtobufMessage $response): AllEventsSlice
    {
        /* @var ReadAllEventsCompleted $response */
        $records = $response->getEvents();

        $resolvedEvents = [];

        foreach ($records as $record) {
            \assert($record instanceof ResolvedEventMessage);

            if ($event = $record->getEvent()) {
                $event = EventMessageConverter::convertEventRecordMessageToEventRecord($record->getEvent());
            }

            if ($link = $record->getLink()) {
                $link = EventMessageConverter::convertEventRecordMessageToEventRecord($link);
            }

            $resolvedEvents[] = new ResolvedEvent($event, $link, new Position($record->getCommitPosition(), $record->getPreparePosition()));
        }

        return new AllEventsSlice(
            ReadDirection::forward(),
            new Position($response->getCommitPosition(), $response->getPreparePosition()),
            new Position($response->getNextCommitPosition(), $response->getNextPreparePosition()),
            $resolvedEvents
        );
    }

    public function name(): string
    {
        return 'ReadAllEventsForward';
    }

    public function __toString(): string
    {
        return \sprintf('Position: %s, MaxCount: %d, ResolveLinkTos: %s, RequireMaster: %s',
            $this->position,
            $this->maxCount,
            $this->resolveLinkTos ? 'yes' : 'no',
            $this->requireMaster ? 'yes' : 'no'
        );
    }
}
