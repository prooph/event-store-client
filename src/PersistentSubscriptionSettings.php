<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

class PersistentSubscriptionSettings
{
    /**
     * Tells the subscription to resolve link events.
     * @var bool
     */
    private $resolveLinkTos;
    /**
     * Start the subscription from the position-of the event in the stream.
     * If the value is set to `-1` that the subscription should start from
     * where the stream is when the subscription is first connected.
     * @var int
     */
    private $startFrom;
    /**
     * Tells the backend to measure timings on the clients so statistics will contain histograms of them.
     * @var bool
     */
    private $extraStatistics;
    /**
     * The amount of time the system should try to checkpoint after.
     * @var int
     */
    private $checkPointAfterMilliseconds;
    /**
     * The size of the live buffer (in memory) before resorting to paging.
     * @var int
     */
    private $liveBufferSize;
    /**
     * The size of the read batch when in paging mode.
     * @var int
     */
    private $readBatchSize;
    /**
     * The number of messages that should be buffered when in paging mode.
     * @var int
     */
    private $bufferSize;
    /**
     * The maximum number of messages not checkpointed before forcing a checkpoint.
     * @var int
     */
    private $maxCheckPointCount;
    /**
     * Sets the number of times a message should be retried before considered a bad message.
     * @var int
     */
    private $maxRetryCount;
    /**
     * Sets the maximum number of allowed subscribers.
     * @var int
     */
    private $maxSubscriberCount;
    /**
     * Sets the timeout for a client before the message will be retried.
     * @var int
     */
    private $messageTimeoutMilliseconds;
    /**
     * The minimum number of messages to write a checkpoint for.
     * @var int
     */
    private $minCheckPointCount;
    /** @var NamedConsumerStrategy */
    private $namedConsumerStrategy;

    private const Int32Max = 2147483647;

    public static function default(): PersistentSubscriptionSettings
    {
        return new self(
            true,
            -1,
            false,
            2000,
            500,
            10,
            20,
            1000,
            500,
            0,
            30000,
            10,
            NamedConsumerStrategy::roundRobin()
        );
    }

    public function __construct(
        bool $resolveLinkTos,
        int $startFrom,
        bool $extraStatistics,
        int $checkPointAfterMilliseconds,
        int $liveBufferSize,
        int $readBatchSize,
        int $bufferSize,
        int $maxCheckPointCount,
        int $maxRetryCount,
        int $maxSubscriberCount,
        int $messageTimeoutMilliseconds,
        int $minCheckPointCount,
        NamedConsumerStrategy $namedConsumerStrategy
    ) {
        if ($checkPointAfterMilliseconds > self::Int32Max) {
            throw new \InvalidArgumentException('checkPointAfterMilliseconds must smaller then ' . self::Int32Max);
        }

        if ($messageTimeoutMilliseconds > self::Int32Max) {
            throw new \InvalidArgumentException('messageTimeoutMilliseconds must smaller then ' . self::Int32Max);
        }

        $this->resolveLinkTos = $resolveLinkTos;
        $this->startFrom = $startFrom;
        $this->extraStatistics = $extraStatistics;
        $this->checkPointAfterMilliseconds = $checkPointAfterMilliseconds;
        $this->liveBufferSize = $liveBufferSize;
        $this->readBatchSize = $readBatchSize;
        $this->bufferSize = $bufferSize;
        $this->maxCheckPointCount = $maxCheckPointCount;
        $this->maxRetryCount = $maxRetryCount;
        $this->maxSubscriberCount = $maxSubscriberCount;
        $this->messageTimeoutMilliseconds = $messageTimeoutMilliseconds;
        $this->minCheckPointCount = $minCheckPointCount;
        $this->namedConsumerStrategy = $namedConsumerStrategy;
    }

    public function resolveLinkTos(): bool
    {
        return $this->resolveLinkTos;
    }

    public function startFrom(): int
    {
        return $this->startFrom;
    }

    public function extraStatistics(): bool
    {
        return $this->extraStatistics;
    }

    public function checkPointAfterMilliseconds(): int
    {
        return $this->checkPointAfterMilliseconds;
    }

    public function liveBufferSize(): int
    {
        return $this->liveBufferSize;
    }

    public function readBatchSize(): int
    {
        return $this->readBatchSize;
    }

    public function bufferSize(): int
    {
        return $this->bufferSize;
    }

    public function maxCheckPointCount(): int
    {
        return $this->maxCheckPointCount;
    }

    public function maxRetryCount(): int
    {
        return $this->maxRetryCount;
    }

    public function maxSubscriberCount(): int
    {
        return $this->maxSubscriberCount;
    }

    public function messageTimeoutMilliseconds(): int
    {
        return $this->messageTimeoutMilliseconds;
    }

    public function minCheckPointCount(): int
    {
        return $this->minCheckPointCount;
    }

    public function namedConsumerStrategy(): NamedConsumerStrategy
    {
        return $this->namedConsumerStrategy;
    }

    public function toArray(): array
    {
        return [
            'resolveLinkTos' => $this->resolveLinkTos,
            'startFrom' => $this->startFrom,
            'extraStatistics' => $this->extraStatistics,
            'checkPointAfterMilliseconds' => $this->checkPointAfterMilliseconds,
            'liveBufferSize' => $this->liveBufferSize,
            'readBatchSize' => $this->readBatchSize,
            'bufferSize' => $this->bufferSize,
            'maxCheckPointCount' => $this->maxCheckPointCount,
            'maxRetryCount' => $this->maxRetryCount,
            'maxSubscriberCount' => $this->maxSubscriberCount,
            'messageTimeoutMilliseconds' => $this->messageTimeoutMilliseconds,
            'minCheckPointCount' => $this->minCheckPointCount,
            'namedConsumerStrategy' => $this->namedConsumerStrategy->name(),
        ];
    }
}
