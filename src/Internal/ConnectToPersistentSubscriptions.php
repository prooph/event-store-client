<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Prooph\EventStoreClient\EventId;
use Prooph\EventStoreClient\PersistentSubscriptionNakEventAction;

/** @internal */
interface ConnectToPersistentSubscriptions
{
    /** @param EventId[] $eventIds */
    public function notifyEventsProcessed(array $eventIds): void;

    /**
     * @param EventId[] $eventIds
     * @param PersistentSubscriptionNakEventAction $action
     * @param string $reason
     */
    public function notifyEventsFailed(
        array $eventIds,
        PersistentSubscriptionNakEventAction $action,
        string $reason
    ): void;

    public function unsubscribe(): void;
}
