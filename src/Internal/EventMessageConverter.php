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

namespace Prooph\EventStoreClient\Internal;

use DateTimeImmutable;
use DateTimeZone;
use Prooph\EventStoreClient\EventId;
use Prooph\EventStoreClient\EventRecord;
use Prooph\EventStoreClient\Messages\ClientMessages\EventRecord as EventRecordMessage;
use Prooph\EventStoreClient\Messages\ClientMessages\ResolvedEvent as ResolvedEventMessage;
use Prooph\EventStoreClient\Messages\ClientMessages\ResolvedIndexedEvent as ResolvedIndexedEventMessage;
use Prooph\EventStoreClient\Position;
use Prooph\EventStoreClient\ResolvedEvent;

/** @internal */
class EventMessageConverter
{
    public static function convertEventRecordMessageToEventRecord(EventRecordMessage $message): EventRecord
    {
        $epoch = (string) $message->getCreatedEpoch();
        $date = \substr($epoch, 0, -3);
        $micro = \substr($epoch, -3);

        $created = DateTimeImmutable::createFromFormat(
            'U.u',
            $date . '.' . $micro,
            new DateTimeZone('UTC')
        );

        return new EventRecord(
            $message->getEventStreamId(),
            $message->getEventNumber(),
            EventId::fromBinary($message->getEventId()),
            $message->getEventType(),
            $message->getDataContentType() === 1,
            $message->getData(),
            $message->getMetadata(),
            $created
        );
    }

    public static function convertResolvedEventMessageToResolvedEvent(ResolvedEventMessage $message): ResolvedEvent
    {
        $event = $message->getEvent();
        $link = $message->getLink();

        return new ResolvedEvent(
            $event ? self::convertEventRecordMessageToEventRecord($event) : null,
            $link ? self::convertEventRecordMessageToEventRecord($link) : null,
            new Position($message->getCommitPosition(), $message->getPreparePosition())
        );
    }

    public static function convertResolvedIndexedEventMessageToResolvedEvent(ResolvedIndexedEventMessage $message): ResolvedEvent
    {
        $event = $message->getEvent();
        $link = $message->getLink();

        return new ResolvedEvent(
            $event ? self::convertEventRecordMessageToEventRecord($event) : null,
            $link ? self::convertEventRecordMessageToEventRecord($link) : null,
            null
        );
    }
}
