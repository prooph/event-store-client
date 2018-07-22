<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Prooph\EventStoreClient\Internal\ResolvedEvent as InternalResolvedEvent;

class PersistentSubscriptionResolvedEvent implements InternalResolvedEvent
{
    /** @var int|null */
    private $retryCount;
    /** @var ResolvedEvent */
    private $event;

    /** @internal */
    public function __construct(ResolvedEvent $event, ?int $retryCount)
    {
        $this->event = $event;
        $this->retryCount = $retryCount;
    }

    public function retryCount(): ?int
    {
        return $this->retryCount;
    }

    public function event(): ResolvedEvent
    {
        return $this->event;
    }

    public function originalEvent(): ?EventRecord
    {
        return $this->event->originalEvent();
    }

    public function originalPosition(): ?Position
    {
        return $this->event->originalPosition();
    }

    public function originalStreamName(): string
    {
        return $this->event->originalStreamName();
    }

    public function originalEventNumber(): int
    {
        return $this->event->originalEventNumber();
    }
}
