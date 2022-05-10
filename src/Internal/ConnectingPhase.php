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

namespace Prooph\EventStoreClient\Internal;

enum ConnectingPhase: int
{
    case Invalid = 0;
    case Reconnecting = 1;
    case EndPointDiscovery = 2;
    case ConnectionEstablishing = 3;
    case Authentication = 4;
    case Identification = 5;
    case Connected = 6;
}
