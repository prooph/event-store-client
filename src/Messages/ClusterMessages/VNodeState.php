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

namespace Prooph\EventStoreClient\Messages\ClusterMessages;

enum VNodeState: string
{
    case Initializing = 'Initializing';
    case ReadOnlyLeaderless = 'ReadOnlyLeaderless';
    case Unknown = 'Unknown';
    case PreReadOnlyReplica = 'PreReadOnlyReplica';
    case PreReplica = 'PreReplica';
    case CatchingUp = 'CatchingUp';
    case Clone_ = 'Clone';
    case ReadOnlyReplica = 'ReadOnlyReplica';
    case Slave = 'Slave';
    case Follower = 'Follower';
    case PreMaster = 'PreMaster';
    case PreLeader = 'PreLeader';
    case Master = 'Master';
    case Leader = 'Leader';
    case Manager = 'Manager';
    case ShuttingDown = 'ShuttingDown';
    case Shutdown = 'Shutdown';
}
