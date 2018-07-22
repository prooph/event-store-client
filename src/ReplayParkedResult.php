<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

/** @internal */
class ReplayParkedResult
{
    /** @var string */
    private $correlationId;
    /** @var string */
    private $reason;
    /** @var ReplayParkedStatus */
    private $status;

    public function __construct(string $correlationId, string $reason, ReplayParkedStatus $status)
    {
        $this->correlationId = $correlationId;
        $this->reason = $reason;
        $this->status = $status;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function status(): ReplayParkedStatus
    {
        return $this->status;
    }
}
