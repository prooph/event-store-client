<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class EventId
{
    private $uuid;

    public static function generate(): EventId
    {
        return new self(Uuid::uuid4());
    }

    public static function fromString(string $eventId): EventId
    {
        return new self(Uuid::fromString($eventId));
    }

    public static function fromBinary(string $bytes): EventId
    {
        return new self(Uuid::fromBytes($bytes));
    }

    private function __construct(UuidInterface $eventId)
    {
        $this->uuid = $eventId;
    }

    public function toString(): string
    {
        return $this->uuid->toString();
    }

    public function toBinary(): string
    {
        return $this->uuid->getBytes();
    }

    public function __toString(): string
    {
        return $this->uuid->toString();
    }

    public function equals(EventId $other): bool
    {
        return $this->uuid->equals($other->uuid);
    }
}
