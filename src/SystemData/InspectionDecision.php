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

namespace Prooph\EventStoreClient\SystemData;

enum InspectionDecision: int
{
    case DoNothing = 0;
    case EndOperation = 1;
    case Retry = 2;
    case Reconnect = 3;
    case Subscribed = 4;
}
