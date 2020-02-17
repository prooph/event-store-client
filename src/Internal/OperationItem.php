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

namespace Prooph\EventStoreClient\Internal;

use DateTimeImmutable;
use Prooph\EventStore\Util\DateTime;
use Prooph\EventStore\Util\Guid;
use Prooph\EventStoreClient\ClientOperations\ClientOperation;

/** @internal */
class OperationItem
{
    private static int $nextSeqNo = -1;
    private int $segNo;
    private ClientOperation $operation;
    private int $maxRetries;
    private int $timeout;
    private DateTimeImmutable $created;
    private string $connectionId;
    private string $correlationId;
    private int $retryCount;
    private DateTimeImmutable $lastUpdated;

    public function __construct(ClientOperation $operation, int $maxRetries, int $timeout)
    {
        $this->segNo = ++self::$nextSeqNo;
        $this->operation = $operation;
        $this->maxRetries = $maxRetries;
        $this->timeout = $timeout;
        $this->created = DateTime::utcNow();
        $this->correlationId = Guid::generateAsHex();
        $this->retryCount = 0;
        $this->lastUpdated = $this->created;
    }

    public function segNo(): int
    {
        return $this->segNo;
    }

    public function operation(): ClientOperation
    {
        return $this->operation;
    }

    public function maxRetries(): int
    {
        return $this->maxRetries;
    }

    public function timeout(): int
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

    public function retryCount(): int
    {
        return $this->retryCount;
    }

    public function incRetryCount(): void
    {
        ++$this->retryCount;
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

    /**
     * @param DateTimeImmutable $lastUpdated
     */
    public function setLastUpdated(DateTimeImmutable $lastUpdated): void
    {
        $this->lastUpdated = $lastUpdated;
    }

    public function __toString(): string
    {
        return \sprintf(
            'Operation %s (%s): %s, retry count: %d, created: %s, last updated: %s',
            $this->operation->name(),
            $this->correlationId,
            (string) $this->operation,
            $this->retryCount,
            DateTime::format($this->created),
            DateTime::format($this->lastUpdated)
        );
    }
}
