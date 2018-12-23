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

namespace Prooph\EventStoreClient\Internal;

use Prooph\EventStore\EventData;
use Prooph\EventStoreClient\Messages\ClientMessages\NewEvent;

/** @internal */
class NewEventConverter
{
    public static function convert(EventData $eventData): NewEvent
    {
        $event = new NewEvent();

        if ($eventData->isJson()) {
            $contentType = 1;
        } else {
            $contentType = 2;
        }

        $event->setEventId($eventData->eventId()->toBinary());
        $event->setDataContentType($contentType);
        $event->setMetadataContentType($contentType);
        $event->setData($eventData->data());
        $event->setMetadata($eventData->metaData());
        $event->setEventType($eventData->eventType());

        return $event;
    }
}
