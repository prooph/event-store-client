<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

/** @internal */
class ReconnectionInfo
{
    /** @var int */
    private $reconnectionAttempt;
    /** @var int */
    private $timestamp;

    public function __construct(int $reconnectionAttempt, int $timestamp)
    {
        $this->reconnectionAttempt = $reconnectionAttempt;
        $this->timestamp = $timestamp;
    }

    public function reconnectionAttempt(): int
    {
        return $this->reconnectionAttempt;
    }

    public function timestamp(): int
    {
        return $this->timestamp;
    }
}
