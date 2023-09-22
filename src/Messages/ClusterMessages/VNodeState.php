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

namespace Prooph\EventStoreClient\Messages\ClusterMessages;

enum VNodeState: int
{
    case Initializing = 1;
    case ReadOnlyLeaderless = 2;
    case Unknown = 3;
    case PreReadOnlyReplica = 4;
    case PreReplica = 5;
    case CatchingUp = 6;
    case Clone = 7;
    case ReadOnlyReplica = 8;
    case Slave = 9;
    case Follower = 10;
    case PreMaster = 11;
    case PreLeader = 12;
    case Master = 13;
    case Leader = 14;
    case Manager = 15;
    case ShuttingDown = 16;
    case Shutdown = 17;
}
