<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2019 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient\Helper;

use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;

/** @internal */
class TestEvent
{
    private function __construct()
    {
    }

    public static function newTestEvent(?EventId $eventId = null, ?string $data = null, ?string $metadata = null): EventData
    {
        if (null === $eventId) {
            $eventId = EventId::generate();
        }

        return new EventData($eventId, 'TestEvent', false, $data ?? $eventId->toString(), $metadata ?? 'metadata');
    }

    /** @return EventData[] */
    public static function newAmount(int $amount): array
    {
        $events = [];

        for ($i = 0; $i < $amount; $i++) {
            $events[] = self::newTestEvent();
        }

        return $events;
    }
}
