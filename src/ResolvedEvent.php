<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Prooph\EventStoreClient\Internal\ResolvedEvent as InternalResolvedEvent;

/**
 * A structure representing a single event or an resolved link event.
 */
class ResolvedEvent implements InternalResolvedEvent
{
    /**
     * The event, or the resolved link event if this is a link event
     * @var EventRecord|null
     */
    private $event;
    /**
     * The link event if this ResolvedEvent is a link event.
     * @var EventRecord|null
     */
    private $link;
    /**
     * Returns the event that was read or which triggered the subscription.
     *
     * If this ResolvedEvent represents a link event, the Link
     * will be the OriginalEvent otherwise it will be the event.
     * @var EventRecord|null
     */
    private $originalEvent;
    /**
     * Indicates whether this ResolvedEvent is a resolved link event.
     * @var bool
     */
    private $isResolved;
    /**
     * The logical position of the OriginalEvent
     * @var Position|null
     */
    private $originalPosition;

    /** @internal */
    public function __construct(?EventRecord $event, ?EventRecord $link, ?Position $originalPosition)
    {
        $this->event = $event;
        $this->link = $link;
        $this->originalEvent = $link ?? $event;
        $this->isResolved = null !== $link;
        $this->originalPosition = $originalPosition;
    }

    public function event(): ?EventRecord
    {
        return $this->event;
    }

    public function link(): ?EventRecord
    {
        return $this->link;
    }

    public function originalEvent(): ?EventRecord
    {
        return $this->originalEvent;
    }

    public function isResolved(): bool
    {
        return $this->isResolved;
    }

    public function originalPosition(): ?Position
    {
        return $this->originalPosition;
    }

    public function originalStreamName(): string
    {
        return $this->originalEvent->eventStreamId();
    }

    public function originalEventNumber(): int
    {
        return $this->originalEvent->eventNumber();
    }
}
