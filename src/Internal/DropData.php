<?php
/**
 * This file is part of the prooph/event-store-client.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Prooph\EventStoreClient\SubscriptionDropReason;
use Throwable;

/** @internal */
class DropData
{
    /** @var SubscriptionDropReason */
    private $reason;
    /** @var Throwable */
    private $error;

    public function __construct(SubscriptionDropReason $reason, Throwable $error)
    {
        $this->reason = $reason;
        $this->error = $error;
    }

    public function reason(): SubscriptionDropReason
    {
        return $this->reason;
    }

    public function error(): Throwable
    {
        return $this->error;
    }
}
