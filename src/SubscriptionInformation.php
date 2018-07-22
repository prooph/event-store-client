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

namespace Prooph\EventStoreClient;

class SubscriptionInformation
{
    /** @var string */
    private $eventStreamId;
    /** @var string */
    private $groupName;
    /** @var string */
    private $status;
    /** @var float */
    private $averageItemsPerSecond;
    /** @var int */
    private $totalItemsProcessed;
    /** @var int */
    private $lastProcessedEventNumber;
    /** @var int */
    private $lastKnownEventNumber;
    /** @var int */
    private $connectionCount;
    /** @var int */
    private $totalInFlightMessages;

    /** @internal */
    public function __construct(
        string $eventStreamId,
        string $groupName,
        string $status,
        float $averageItemsPerSecond,
        int $totalItemsProcessed,
        int $lastProcessedEventNumber,
        int $lastKnownEventNumber,
        int $connectionCount,
        int $totalInFlightMessages
    ) {
        $this->eventStreamId = $eventStreamId;
        $this->groupName = $groupName;
        $this->status = $status;
        $this->averageItemsPerSecond = $averageItemsPerSecond;
        $this->totalItemsProcessed = $totalItemsProcessed;
        $this->lastProcessedEventNumber = $lastProcessedEventNumber;
        $this->lastKnownEventNumber = $lastKnownEventNumber;
        $this->connectionCount = $connectionCount;
        $this->totalInFlightMessages = $totalInFlightMessages;
    }

    public function eventStreamId(): string
    {
        return $this->eventStreamId;
    }

    public function groupName(): string
    {
        return $this->groupName;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function averageItemsPerSecond(): float
    {
        return $this->averageItemsPerSecond;
    }

    public function totalItemsProcessed(): int
    {
        return $this->totalItemsProcessed;
    }

    public function lastProcessedEventNumber(): int
    {
        return $this->lastProcessedEventNumber;
    }

    public function lastKnownEventNumber(): int
    {
        return $this->lastKnownEventNumber;
    }

    public function connectionCount(): int
    {
        return $this->connectionCount;
    }

    public function totalInFlightMessages(): int
    {
        return $this->totalInFlightMessages;
    }
}
