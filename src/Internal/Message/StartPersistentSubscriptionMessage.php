<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2020 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2020 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\Message;

use Amp\Deferred;
use Amp\Promise;
use Closure;
use Prooph\EventStore\Async\EventStorePersistentSubscription;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SubscriptionDropReason;
use Prooph\EventStore\UserCredentials;
use Throwable;

/** @internal */
class StartPersistentSubscriptionMessage implements Message
{
    private Deferred $deferred;
    private string $subscriptionId;
    private string $streamId;
    private int $bufferSize;
    private ?UserCredentials $userCredentials;
    /** @var Closure(EventStorePersistentSubscription, ResolvedEvent, null|int): Promise */
    private Closure $eventAppeared;
    /** @var null|Closure(EventStorePersistentSubscription, SubscriptionDropReason, null|Throwable): void */
    private ?Closure $subscriptionDropped;
    private int $maxRetries;
    private int $timeout;

    /**
     * @param Closure(EventStorePersistentSubscription, ResolvedEvent, null|int): Promise $eventAppeared
     * @param null|Closure(EventStorePersistentSubscription, SubscriptionDropReason, null|Throwable): void $subscriptionDropped
     */
    public function __construct(
        Deferred $deferred,
        string $subscriptionId,
        string $streamId,
        int $bufferSize,
        ?UserCredentials $userCredentials,
        Closure $eventAppeared,
        ?Closure $subscriptionDropped,
        int $maxRetries,
        int $timeout
    ) {
        $this->deferred = $deferred;
        $this->subscriptionId = $subscriptionId;
        $this->streamId = $streamId;
        $this->bufferSize = $bufferSize;
        $this->userCredentials = $userCredentials;
        $this->eventAppeared = $eventAppeared;
        $this->subscriptionDropped = $subscriptionDropped;
        $this->maxRetries = $maxRetries;
        $this->timeout = $timeout;
    }

    /** @psalm-pure */
    public function deferred(): Deferred
    {
        return $this->deferred;
    }

    /** @psalm-pure */
    public function subscriptionId(): string
    {
        return $this->subscriptionId;
    }

    /** @psalm-pure */
    public function streamId(): string
    {
        return $this->streamId;
    }

    /** @psalm-pure */
    public function bufferSize(): int
    {
        return $this->bufferSize;
    }

    /** @psalm-pure */
    public function userCredentials(): ?UserCredentials
    {
        return $this->userCredentials;
    }

    /**
     * @return Closure(EventStorePersistentSubscription, ResolvedEvent, null|int): Promise
     *
     * @psalm-pure
     */
    public function eventAppeared(): Closure
    {
        return $this->eventAppeared;
    }

    /**
     * @return null|Closure(EventStorePersistentSubscription, SubscriptionDropReason, null|Throwable): void
     *
     * @psalm-pure
     */
    public function subscriptionDropped(): ?Closure
    {
        return $this->subscriptionDropped;
    }

    /** @psalm-pure */
    public function maxRetries(): int
    {
        return $this->maxRetries;
    }

    /** @psalm-pure */
    public function timeout(): int
    {
        return $this->timeout;
    }

    /** @psalm-pure */
    public function promise(): ?Promise
    {
        return $this->deferred->promise();
    }

    /** @psalm-pure */
    public function __toString(): string
    {
        return 'StartPersistentSubscriptionMessage';
    }
}
