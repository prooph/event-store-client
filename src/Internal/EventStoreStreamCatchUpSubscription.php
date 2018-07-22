<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Amp\Delayed;
use Amp\Promise;
use Generator;
use Prooph\EventStoreClient\CatchUpSubscriptionSettings;
use Prooph\EventStoreClient\EventStoreAsyncConnection;
use Prooph\EventStoreClient\Exception\StreamDeletedException;
use Prooph\EventStoreClient\ResolvedEvent;
use Prooph\EventStoreClient\SliceReadStatus;
use Prooph\EventStoreClient\StreamEventsSlice;
use Prooph\EventStoreClient\SubscriptionDropReason;
use Prooph\EventStoreClient\UserCredentials;
use Psr\Log\LoggerInterface as Logger;
use function Amp\call;

class EventStoreStreamCatchUpSubscription extends EventStoreCatchUpSubscription
{
    /** @var int */
    private $nextReadEventNumber;
    /** @var int */
    private $lastProcessedEventNumber;

    /**
     * @internal
     *
     * @param EventStoreAsyncConnection $connection
     * @param Logger $logger,
     * @param string $streamId
     * @param int|null $fromEventNumberExclusive
     * @param null|UserCredentials $userCredentials
     * @param callable(EventStoreCatchUpSubscription $subscription, ResolvedEvent $event): Promise $eventAppeared
     * @param null|callable(EventStoreCatchUpSubscription $subscription): void $liveProcessingStarted
     * @param null|callable(EventStoreCatchUpSubscription $subscription, SubscriptionDropReason $reason, Throwable $exception):void $subscriptionDropped
     * @param CatchUpSubscriptionSettings $settings
     */
    public function __construct(
        EventStoreAsyncConnection $connection,
        Logger $logger,
        string $streamId,
        ?int $fromEventNumberExclusive, // if null from the very beginning
        ?UserCredentials $userCredentials,
        callable $eventAppeared,
        ?callable $liveProcessingStarted,
        ?callable $subscriptionDropped,
        CatchUpSubscriptionSettings $settings
    ) {
        parent::__construct(
            $connection,
            $logger,
            $streamId,
            $userCredentials,
            $eventAppeared,
            $liveProcessingStarted,
            $subscriptionDropped,
            $settings
        );

        $this->lastProcessedEventNumber = $fromEventNumberExclusive ?? -1;
        $this->nextReadEventNumber = $fromEventNumberExclusive ?? 0;
    }

    public function lastProcessedEventNumber(): int
    {
        return $this->lastProcessedEventNumber;
    }

    /** @return Promise<void> */
    protected function readEventsTillAsync(
        EventStoreAsyncConnection $connection,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials,
        ?int $lastCommitPosition,
        ?int $lastEventNumber
    ): Promise {
        return $this->readEventsInternalAsync($connection, $resolveLinkTos, $userCredentials, $lastEventNumber);
    }

    /** @return Promise<void> */
    private function readEventsInternalAsync(
        EventStoreAsyncConnection $connection,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials,
        ?int $lastEventNumber
    ): Promise {
        return call(function () use ($connection, $resolveLinkTos, $userCredentials, $lastEventNumber): Generator {
            do {
                $slice = yield $connection->readStreamEventsForwardAsync(
                    $this->streamId(),
                    $this->nextReadEventNumber,
                    $this->readBatchSize,
                    $resolveLinkTos,
                    $userCredentials
                );

                $shouldStopOrDone = yield $this->readEventsCallbackAsync($slice, $lastEventNumber);
            } while (! $shouldStopOrDone);
        });
    }

    /** @return Promise<bool> */
    private function readEventsCallbackAsync(StreamEventsSlice $slice, ?int $lastEventNumber): Promise
    {
        return call(function () use ($slice, $lastEventNumber): Generator {
            $shouldStopOrDone = $this->shouldStop || yield $this->processEventsAsync($lastEventNumber, $slice);

            if ($shouldStopOrDone && $this->verbose) {
                $this->log->debug(\sprintf(
                    'Catch-up Subscription %s to %s: finished reading events, nextReadEventNumber = %d',
                    $this->subscriptionName(),
                    $this->isSubscribedToAll() ? '<all>' : $this->streamId(),
                    $this->nextReadEventNumber
                ));
            }

            return $shouldStopOrDone;
        });
    }

    /** @return Promise<bool> */
    private function processEventsAsync(?int $lastEventNumber, StreamEventsSlice $slice): Promise
    {
        return call(function () use ($lastEventNumber, $slice): Generator {
            switch ($slice->status()->value()) {
                case SliceReadStatus::Success:
                    foreach ($slice->events() as $e) {
                        yield $this->tryProcessAsync($e);
                    }
                    $this->nextReadEventNumber = $slice->nextEventNumber();
                    $done = (null === $lastEventNumber) ? $slice->isEndOfStream() : $slice->nextEventNumber() > $lastEventNumber;

                    break;
                case SliceReadStatus::StreamNotFound:
                    if (null !== $lastEventNumber && $lastEventNumber !== -1) {
                        throw new \Exception(\sprintf(
                            'Impossible: stream %s disappeared in the middle of catching up subscription %s',
                            $this->streamId(),
                            $this->subscriptionName()
                        ));
                    }

                    $done = true;

                    break;
                case SliceReadStatus::StreamDeleted:
                    throw StreamDeletedException::with($this->streamId());
            }

            if (! $done && $slice->isEndOfStream()) {
                yield new Delayed(1000); // we are waiting for server to flush its data
            }

            return $done;
        });
    }

    protected function tryProcessAsync(ResolvedEvent $e): Promise
    {
        return call(function () use ($e): Generator {
            $processed = false;

            if ($e->originalEventNumber() > $this->lastProcessedEventNumber) {
                try {
                    yield ($this->eventAppeared)($this, $e);
                } catch (\Throwable $ex) {
                    $this->dropSubscription(SubscriptionDropReason::eventHandlerException(), $ex);

                    throw $ex;
                }

                $this->lastProcessedEventNumber = $e->originalEventNumber();
                $processed = true;
            }

            if ($this->verbose) {
                $this->log->debug(\sprintf(
                    'Catch-up Subscription %s to %s: %s event (%s, %d, %s @ %d)',
                    $this->subscriptionName(),
                    $this->isSubscribedToAll() ? '<all>' : $this->streamId(),
                    $processed ? 'processed' : 'skipping',
                    $e->originalEvent()->eventStreamId(),
                    $e->originalEvent()->eventNumber(),
                    $e->originalEvent()->eventType(),
                    $e->originalEventNumber()
                ));
            }
        });
    }
}
