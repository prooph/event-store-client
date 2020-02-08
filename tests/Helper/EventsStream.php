<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2020 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2020 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient\Helper;

use function Amp\call;
use Amp\Promise;
use Amp\Success;
use Generator;
use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\StreamEventsSlice;

/** @internal */
class EventsStream
{
    private const SLICE_SIZE = 10;

    /** @return Promise<int> */
    public static function count(EventStoreConnection $connection, string $stream): Promise
    {
        return call(function () use ($connection, $stream): Generator {
            $result = 0;

            while (true) {
                $slice = yield $connection->readStreamEventsForwardAsync($stream, $result, self::SLICE_SIZE, false);
                \assert($slice instanceof StreamEventsSlice);
                $result += \count($slice->events());

                if ($slice->isEndOfStream()) {
                    break;
                }
            }

            return new Success($result);
        });
    }
}
