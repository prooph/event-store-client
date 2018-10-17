<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

    public function originalEvent(): ?RecordedEvent
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
