<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Common;

class SystemConsumerStrategies
{
    // Distributes events to a single client until it is full. Then round robin to the next client.
    public const DispatchToSingle = 'DispatchToSingle';
    // Distribute events to each client in a round robin fashion.
    public const RoundRobin = 'RoundRobin';
    // Distribute events of the same streamId to the same client until it disconnects on a best efforts basis.
    // Designed to be used with indexes such as the category projection.
    public const Pinned = 'Pinned';
}
