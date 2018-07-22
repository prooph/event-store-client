<?php
/**
 * This file is part of the prooph/event-store-client.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use DateTimeImmutable;
use Prooph\EventStoreClient\ClientOperations\ClientOperation;

/** @internal */
class OperationItem
{
    private static $nextSeqNo = -1;
    /** @var int */
    private $segNo;
    /** @var ClientOperation */
    private $operation;
    /** @var int */
    private $maxRetries;
    /** @var int */
    private $timeout;
    /** @var DateTimeImmutable */
    private $created;
    /** @var string */
    private $connectionId;
    /** @var string */
    private $correlationId;
    /** @var int */
    private $retryCount;
    /** @var DateTimeImmutable */
    private $lastUpdated;

    public function __construct(ClientOperation $operation, int $maxRetries, int $timeout)
    {
        $this->segNo = ++self::$nextSeqNo;
        $this->operation = $operation;
        $this->maxRetries = $maxRetries;
        $this->timeout = $timeout;
        $this->created = DateTimeUtil::utcNow();
        $this->correlationId = UuidGenerator::generate();
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
            $this->operation,
            $this->retryCount,
            DateTimeUtil::format($this->created),
            DateTimeUtil::format($this->lastUpdated)
        );
    }
}
