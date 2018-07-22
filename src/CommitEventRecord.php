<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

class CommitEventRecord
{
    /** @var EventRecord */
    private $event;
    /** @var int */
    public $commitPosition;

    /** @internal */
    public function __construct(EventRecord $event, int $commitPosition)
    {
        $this->event = $event;
        $this->commitPosition = $commitPosition;
    }

    public function event(): EventRecord
    {
        return $this->event;
    }

    public function commitPosition(): int
    {
        return $this->commitPosition;
    }
}
