<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

class WriteResult
{
    /** @var int */
    private $nextExpectedVersion;
    /** @var Position */
    private $logPosition;

    public function __construct(int $nextExpectedVersion, Position $logPosition)
    {
        $this->nextExpectedVersion = $nextExpectedVersion;
        $this->logPosition = $logPosition;
    }

    public function nextExpectedVersion(): int
    {
        return $this->nextExpectedVersion;
    }

    public function logPosition(): Position
    {
        return $this->logPosition;
    }
}
