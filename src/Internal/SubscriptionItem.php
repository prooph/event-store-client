<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2022 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2022 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use DateTimeImmutable;
use Prooph\EventStore\Util\DateTime;
use Prooph\EventStore\Util\Guid;
use Prooph\EventStoreClient\ClientOperations\SubscriptionOperation;

/** @internal  */
class SubscriptionItem
{
    private DateTimeImmutable $created;

    private string $connectionId = '';

    private string $correlationId;

    private bool $isSubscribed = false;

    private int $retryCount = 0;

    private DateTimeImmutable $lastUpdated;

    public function __construct(private SubscriptionOperation $operation, private int $maxRetries, private float $timeout)
    {
        $this->created = DateTime::utcNow();
        $this->correlationId = Guid::generateAsHex();
        $this->lastUpdated = $this->created;
    }

    public function operation(): SubscriptionOperation
    {
        return $this->operation;
    }

    public function maxRetries(): int
    {
        return $this->maxRetries;
    }

    public function timeout(): float
    {
        return $this->timeout;
    }

    public function created(): DateTimeImmutable
    {
        return $this->created;
    }

    public function connectionId(): string
    {
        return $this->connectionId;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function isSubscribed(): bool
    {
        return $this->isSubscribed;
    }

    public function retryCount(): int
    {
        return $this->retryCount;
    }

    public function lastUpdated(): DateTimeImmutable
    {
        return $this->lastUpdated;
    }

    public function setConnectionId(string $connectionId): void
    {
        $this->connectionId = $connectionId;
    }

    public function setCorrelationId(string $correlationId): void
    {
        $this->correlationId = $correlationId;
    }

    public function setIsSubscribed(bool $isSubscribed): void
    {
        $this->isSubscribed = $isSubscribed;
    }

    public function incRetryCount(): void
    {
        ++$this->retryCount;
    }

    public function setLastUpdated(DateTimeImmutable $lastUpdated): void
    {
        $this->lastUpdated = $lastUpdated;
    }

    public function __toString(): string
    {
        return \sprintf(
            'Subscription %s (%s): %s, is subscribed: %s, retry count: %d, created: %s, last updated: %s',
            $this->operation->name(),
            $this->correlationId,
            (string) $this->operation,
            $this->isSubscribed ? 'yes' : 'no',
            $this->retryCount,
            DateTime::format($this->created),
            DateTime::format($this->lastUpdated)
        );
    }
}
