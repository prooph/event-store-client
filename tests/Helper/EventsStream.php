<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2022 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2022 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient\Helper;

use Prooph\EventStore\EventStoreConnection;

/** @internal */
class EventsStream
{
    private const SliceSize = 10;

    public static function count(EventStoreConnection $connection, string $stream): int
    {
        $result = 0;

        while (true) {
            $slice = $connection->readStreamEventsForward($stream, $result, self::SliceSize, false);
            $result += \count($slice->events());

            if ($slice->isEndOfStream()) {
                break;
            }
        }

        return $result;
    }
}
