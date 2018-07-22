<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Prooph\EventStoreClient\SubscriptionDropReason;

/** @internal */
class DropData
{
    /** @var SubscriptionDropReason */
    private $reason;
    /** @var \Throwable */
    private $error;

    public function __construct(SubscriptionDropReason $reason, \Throwable $error)
    {
        $this->reason = $reason;
        $this->error = $error;
    }

    public function reason(): SubscriptionDropReason
    {
        return $this->reason;
    }

    public function error(): \Throwable
    {
        return $this->error;
    }
}
