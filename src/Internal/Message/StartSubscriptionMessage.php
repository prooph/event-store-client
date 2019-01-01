<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2019 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\Message;

use Amp\Deferred;
use Prooph\EventStore\EventAppearedOnAsyncSubscription;
use Prooph\EventStore\SubscriptionDropped;
use Prooph\EventStore\UserCredentials;

/** @internal  */
class StartSubscriptionMessage implements Message
{
    /** @var Deferred */
    private $deferred;
    /** @var string */
    private $streamId;
    /** @var bool */
    private $resolveTo;
    /** @var UserCredentials|null */
    private $userCredentials;
    /** @var EventAppearedOnAsyncSubscription */
    private $eventAppeared;
    /** @var SubscriptionDropped|null */
    private $subscriptionDropped;
    /** @var int */
    private $maxRetries;
    /** @var int */
    private $timeout;

    public function __construct(
        Deferred $deferred,
        string $streamId,
        bool $resolveTo,
        ?UserCredentials $userCredentials,
        EventAppearedOnAsyncSubscription $eventAppeared,
        ?SubscriptionDropped $subscriptionDropped,
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

    public function deferred(): Deferred
    {
        return $this->deferred;
    }

    public function streamId(): string
    {
        return $this->streamId;
    }

    public function resolveTo(): bool
    {
        return $this->resolveTo;
    }

    public function userCredentials(): ?UserCredentials
    {
        return $this->userCredentials;
    }

    public function eventAppeared(): EventAppearedOnAsyncSubscription
    {
        return $this->eventAppeared;
    }

    public function subscriptionDropped(): ?SubscriptionDropped
    {
        return $this->subscriptionDropped;
    }

    public function maxRetries(): int
    {
        return $this->maxRetries;
    }

    public function timeout(): int
    {
        return $this->timeout;
    }

    public function __toString(): string
    {
        return 'StartSubscriptionMessage';
    }
}
