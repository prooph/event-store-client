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
use Prooph\EventStore\Async\EventAppearedOnSubscription;
use Prooph\EventStore\EventStoreSubscription;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SubscriptionDropped;
use Prooph\EventStore\SubscriptionDropReason;
use Prooph\EventStore\UserCredentials;
use Throwable;

/** @internal */
class StartSubscriptionMessage implements Message
{
    private Deferred $deferred;
    private string $streamId;
    private bool $resolveTo;
    private ?UserCredentials $userCredentials;
    /** @var Closure(EventStoreSubscription, ResolvedEvent): Promise */
    private Closure $eventAppeared;
    /** @var null|Closure(EventStoreSubscription, SubscriptionDropReason, null|Throwable): void */
    private ?Closure $subscriptionDropped;
    private int $maxRetries;
    private int $timeout;

    /**
     * @param Closure(EventStoreSubscription, ResolvedEvent): Promise $eventAppeared
     * @param null|Closure(EventStoreSubscription, SubscriptionDropReason, null|Throwable): void $subscriptionDropped
     */
    public function __construct(
        Deferred $deferred,
        string $streamId,
        bool $resolveTo,
        ?UserCredentials $userCredentials,
        Closure $eventAppeared,
        ?Closure $subscriptionDropped,
        int $maxRetries,
        int $timeout
    ) {
        $this->deferred = $deferred;
        $this->streamId = $streamId;
        $this->resolveTo = $resolveTo;
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
    public function streamId(): string
    {
        return $this->streamId;
    }

    /** @psalm-pure */
    public function resolveTo(): bool
    {
        return $this->resolveTo;
    }

    /** @psalm-pure */
    public function userCredentials(): ?UserCredentials
    {
        return $this->userCredentials;
    }

    /**
     * @return Closure(EventStoreSubscription, ResolvedEvent): Promise
     *
     * @psalm-pure
     */
    public function eventAppeared(): Closure
    {
        return $this->eventAppeared;
    }

    /**
     * @return null|Closure(EventStoreSubscription, SubscriptionDropReason, null|Throwable): void
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
        return 'StartSubscriptionMessage';
    }
}
