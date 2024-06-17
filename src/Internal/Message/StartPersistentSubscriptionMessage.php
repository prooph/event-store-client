<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2024 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2024 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\Message;

use Amp\DeferredFuture;
use Closure;
use Prooph\EventStore\EventStorePersistentSubscription;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SubscriptionDropReason;
use Prooph\EventStore\UserCredentials;
use Throwable;

/**
 * @internal
 *
 * @psalm-immutable
 */
class StartPersistentSubscriptionMessage implements Message
{
    /**
     * @param Closure(EventStorePersistentSubscription, ResolvedEvent, null|int): void $eventAppeared
     * @param null|Closure(EventStorePersistentSubscription, SubscriptionDropReason, null|Throwable): void $subscriptionDropped
     */
    public function __construct(
        private readonly DeferredFuture $deferred,
        private readonly string $subscriptionId,
        private readonly string $streamId,
        private readonly int $bufferSize,
        private readonly ?UserCredentials $userCredentials,
        private readonly Closure $eventAppeared,
        private readonly ?Closure $subscriptionDropped,
        private readonly int $maxRetries,
        private readonly float $timeout
    ) {
    }

    public function deferred(): DeferredFuture
    {
        return $this->deferred;
    }

    public function subscriptionId(): string
    {
        return $this->subscriptionId;
    }

    public function streamId(): string
    {
        return $this->streamId;
    }

    public function bufferSize(): int
    {
        return $this->bufferSize;
    }

    public function userCredentials(): ?UserCredentials
    {
        return $this->userCredentials;
    }

    /**
     * @return Closure(EventStorePersistentSubscription, ResolvedEvent, null|int): void
     */
    public function eventAppeared(): Closure
    {
        return $this->eventAppeared;
    }

    /**
     * @return null|Closure(EventStorePersistentSubscription, SubscriptionDropReason, null|Throwable): void
     */
    public function subscriptionDropped(): ?Closure
    {
        return $this->subscriptionDropped;
    }

    public function maxRetries(): int
    {
        return $this->maxRetries;
    }

    public function timeout(): float
    {
        return $this->timeout;
    }

    public function __toString(): string
    {
        return 'StartPersistentSubscriptionMessage';
    }
}
