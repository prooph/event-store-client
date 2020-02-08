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
use Closure;
use Prooph\EventStore\UserCredentials;

/** @internal  */
class StartPersistentSubscriptionMessage implements Message
{
    private Deferred $deferred;
    private string $subscriptionId;
    private string $streamId;
    private int $bufferSize;
    private ?UserCredentials $userCredentials;
    private Closure $eventAppeared;
    private ?Closure $subscriptionDropped;
    private int $maxRetries;
    private int $timeout;

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

    public function deferred(): Deferred
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

    public function eventAppeared(): Closure
    {
        return $this->eventAppeared;
    }

    public function subscriptionDropped(): ?Closure
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
        return 'StartPersistentSubscriptionMessage';
    }
}
