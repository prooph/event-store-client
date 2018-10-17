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

namespace Prooph\EventStoreClient\Internal;

class PersistentSubscriptionUpdateResult
{
    /** @var PersistentSubscriptionUpdateStatus */
    private $status;

    /** @internal */
    public function __construct(PersistentSubscriptionUpdateStatus $status)
    {
        $this->status = $status;
    }

    public function status(): PersistentSubscriptionUpdateStatus
    {
        return $this->status;
    }
}
