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

namespace Prooph\EventStoreClient\Internal;

use Prooph\EventStore\EventStoreSubscription;
use Prooph\EventStoreClient\ClientOperations\VolatileSubscriptionOperation;

/** @internal */
class VolatileEventStoreSubscription extends EventStoreSubscription
{
    private VolatileSubscriptionOperation $subscriptionOperation;

    public function __construct(
        VolatileSubscriptionOperation $subscriptionOperation,
        string $streamId,
        int $lastCommitPosition,
        ?int $lastEventNumber
    ) {
        parent::__construct($streamId, $lastCommitPosition, $lastEventNumber);

        $this->subscriptionOperation = $subscriptionOperation;
    }

    public function unsubscribe(): void
    {
        $this->subscriptionOperation->unsubscribe();
    }
}
