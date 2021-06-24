<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2021 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2021 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use DateTimeImmutable;
use DateTimeZone;
use Prooph\EventStore\EventId;
use Prooph\EventStore\Position;
use Prooph\EventStore\RecordedEvent;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStoreClient\Messages\ClientMessages\EventRecord as EventRecordMessage;
use Prooph\EventStoreClient\Messages\ClientMessages\ResolvedEvent as ResolvedEventMessage;
use Prooph\EventStoreClient\Messages\ClientMessages\ResolvedIndexedEvent as ResolvedIndexedEventMessage;
use UnexpectedValueException;

/** @internal */
class EventMessageConverter
{
    public static function convertEventRecordMessageToEventRecord(?EventRecordMessage $message): ?RecordedEvent
    {
        if (null === $message) {
            return null;
        }

        $epoch = (string) $message->getCreatedEpoch();
        $date = \substr($epoch, 0, -3);
        $micro = \substr($epoch, -3);

        $created = DateTimeImmutable::createFromFormat(
            'U.u',
            $date . '.' . $micro,
            new DateTimeZone('UTC')
        );

        if (false === $created) {
            throw new UnexpectedValueException('Invalid date format received');
        }

        return new RecordedEvent(
            $message->getEventStreamId(),
            (int) $message->getEventNumber(),
            EventId::fromBinary($message->getEventId()),
            $message->getEventType(),
            $message->getDataContentType() === 1,
            $message->getData(),
            $message->getMetadata(),
            $created
        );
    }

    public static function convertResolvedEventMessageToResolvedEvent(? ResolvedEventMessage $message): ?ResolvedEvent
    {
        if (null === $message) {
            return null;
        }

        return new ResolvedEvent(
            self::convertEventRecordMessageToEventRecord($message->getEvent()),
            self::convertEventRecordMessageToEventRecord($message->getLink()),
            new Position(
                (int) $message->getCommitPosition(),
                (int) $message->getPreparePosition()
            )
        );
    }

    public static function convertResolvedIndexedEventMessageToResolvedEvent(ResolvedIndexedEventMessage $message): ResolvedEvent
    {
        return new ResolvedEvent(
            self::convertEventRecordMessageToEventRecord($message->getEvent()),
            self::convertEventRecordMessageToEventRecord($message->getLink()),
            null
        );
    }
}
