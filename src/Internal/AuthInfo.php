<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

/** @internal */
class AuthInfo
{
    /** @var string */
    private $correlationId;
    /** @var int */
    private $timestamp;

    public function __construct(string $correlationId, int $timestamp)
    {
        $this->correlationId = $correlationId;
        $this->timestamp = $timestamp;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function timestamp(): int
    {
        return $this->timestamp;
    }
}
