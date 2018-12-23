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

namespace ProophTest\EventStoreClient\Helper;

use Prooph\EventStore\EventData;
use Prooph\EventStore\RecordedEvent;

class EventDataComparer
{
    public static function equal(EventData $expected, RecordedEvent $actual): bool
    {
        if (! $expected->eventId()->equals($actual->eventId())) {
            return false;
        }

        if ($expected->eventType() !== $actual->eventType()) {
            return false;
        }

        return $expected->data() === $actual->data() && $expected->metaData() === $actual->metadata();
    }

    /**
     * @param EventData[] $expected
     * @param RecordedEvent[] $actual
     * @return bool
     */
    public static function allEqual(array $expected, array $actual): bool
    {
        if (\count($expected) !== \count($actual)) {
            return false;
        }

        foreach ($expected as $i => $event) {
            if (! self::equal($event, $actual[$i])) {
                return false;
            }
        }

        return true;
    }
}
