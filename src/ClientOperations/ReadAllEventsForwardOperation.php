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
use Prooph\EventStore\AllEventsSlice;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\ServerError;
use Prooph\EventStore\Position;
use Prooph\EventStore\ReadDirection;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\Internal\EventMessageConverter;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadAllEvents;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadAllEventsCompleted;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadAllEventsCompleted\ReadAllResult;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Psr\Log\LoggerInterface as Logger;

/**
 * @internal
 * @extends AbstractOperation<ReadAllEventsCompleted, AllEventsSlice>
 */
class ReadAllEventsForwardOperation extends AbstractOperation
{
    public function __construct(
        Logger $logger,
        DeferredFuture $deferred,
        private readonly bool $requireMaster,
        private readonly Position $position,
        private readonly int $maxCount,
        private readonly bool $resolveLinkTos,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct(
            $logger,
            $deferred,
            $userCredentials,
            TcpCommand::ReadAllEventsForward,
            TcpCommand::ReadAllEventsForwardCompleted,
            ReadAllEventsCompleted::class
        );
    }

    protected function createRequestDto(): Message
    {
        $message = new ReadAllEvents();
        $message->setRequireMaster($this->requireMaster);
        $message->setCommitPosition($this->position->commitPosition());
        $message->setPreparePosition($this->position->preparePosition());
        $message->setMaxCount($this->maxCount);
        $message->setResolveLinkTos($this->resolveLinkTos);

        return $message;
    }

    /**
     * @param ReadAllEventsCompleted $response
     * @return InspectionResult
     */
    protected function inspectResponse(Message $response): InspectionResult
    {
        switch ($response->getResult()) {
            case ReadAllResult::Success:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::EndOperation, 'Success');
            case ReadAllResult::Error:
                $this->fail(new ServerError($response->getError()));

                return new InspectionResult(InspectionDecision::EndOperation, 'Error');
            case ReadAllResult::AccessDenied:
                $this->fail(AccessDenied::toAllStream());

                return new InspectionResult(InspectionDecision::EndOperation, 'AccessDenied');
            default:
                throw new ServerError('Unexpected ReadAllResult');
        }
    }

    /**
     * @param ReadAllEventsCompleted $response
     * @return AllEventsSlice
     */
    protected function transformResponse(Message $response): AllEventsSlice
    {
        $resolvedEvents = [];

        foreach ($response->getEvents() as $record) {
            $resolvedEvents[] = new ResolvedEvent(
                EventMessageConverter::convertEventRecordMessageToEventRecord($record->getEvent()),
                EventMessageConverter::convertEventRecordMessageToEventRecord($record->getLink()),
                new Position(
                    $record->getCommitPosition(),
                    $record->getPreparePosition()
                )
            );
        }

        return new AllEventsSlice(
            ReadDirection::Forward,
            new Position((int) $response->getCommitPosition(), (int) $response->getPreparePosition()),
            new Position((int) $response->getNextCommitPosition(), (int) $response->getNextPreparePosition()),
            $resolvedEvents
        );
    }

    public function name(): string
    {
        return 'ReadAllEventsForward';
    }

    public function __toString(): string
    {
        return \sprintf(
            'Position: %s, MaxCount: %d, ResolveLinkTos: %s, RequireMaster: %s',
            (string) $this->position,
            $this->maxCount,
            $this->resolveLinkTos ? 'yes' : 'no',
            $this->requireMaster ? 'yes' : 'no'
        );
    }
}
