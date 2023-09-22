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

use function Amp\async;
use function Amp\delay;
use Closure;
use Exception;
use Prooph\EventStore\CatchUpSubscriptionSettings;
use Prooph\EventStore\EventStoreConnection;
use Prooph\EventStore\EventStoreStreamCatchUpSubscription as EventStoreStreamCatchUpSubscriptionInterface;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\SubscriptionDropReason;
use Prooph\EventStore\UserCredentials;
use Psr\Log\LoggerInterface as Logger;
use Throwable;

class EventStoreStreamCatchUpSubscription extends EventStoreCatchUpSubscription implements EventStoreStreamCatchUpSubscriptionInterface
{
    private int $nextReadEventNumber;

    private int $lastProcessedEventNumber;

    /**
     * @param Closure(EventStoreCatchUpSubscription, ResolvedEvent): void $eventAppeared
     * @param null|Closure(EventStoreCatchUpSubscription): void $liveProcessingStarted
     * @param null|Closure(EventStoreCatchUpSubscription, SubscriptionDropReason, null|Throwable): void $subscriptionDropped
     *
     * @internal
     */
    public function __construct(
        EventStoreConnection $connection,
        Logger $logger,
        string $streamId,
        ?int $fromEventNumberExclusive, // if null from the very beginning
        ?UserCredentials $userCredentials,
        Closure $eventAppeared,
        ?Closure $liveProcessingStarted,
        ?Closure $subscriptionDropped,
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

    protected function readEventsTill(
        EventStoreConnection $connection,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials,
        ?int $lastCommitPosition,
        ?int $lastEventNumber
    ): void {
        do {
            $slice = $connection->readStreamEventsForward(
                $this->streamId(),
                $this->nextReadEventNumber,
                $this->readBatchSize,
                $resolveLinkTos,
                $userCredentials
            );

            $shouldStopOrDone = $this->readEventsCallback($slice, $lastEventNumber);
        } while (! $shouldStopOrDone);
    }

    private function readEventsCallback(StreamEventsSlice $slice, ?int $lastEventNumber): bool
    {
        $shouldStopOrDone = $this->shouldStop || $this->processEvents($lastEventNumber, $slice);

        if ($shouldStopOrDone && $this->verbose) {
            $this->log->debug(\sprintf(
                'Catch-up Subscription %s to %s: finished reading events, nextReadEventNumber = %d',
                $this->subscriptionName(),
                $this->isSubscribedToAll() ? self::AllStream : $this->streamId(),
                $this->nextReadEventNumber
            ));
        }

        return $shouldStopOrDone;
    }

    private function processEvents(?int $lastEventNumber, StreamEventsSlice $slice): bool
    {
        return async(function () use ($lastEventNumber, $slice): bool {
            switch ($slice->status()) {
                case SliceReadStatus::Success:
                    foreach ($slice->events() as $e) {
                        $this->tryProcess($e);
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
                    throw StreamDeleted::with($this->streamId());
            }

            if (! $done && $slice->isEndOfStream()) {
                delay(1);
            }

            return $done;
        })->await();
    }

    protected function tryProcess(ResolvedEvent $e): void
    {
        $processed = false;

        if ($e->originalEventNumber() > $this->lastProcessedEventNumber) {
            try {
                ($this->eventAppeared)($this, $e);
            } catch (Exception $ex) {
                $this->dropSubscription(SubscriptionDropReason::EventHandlerException, $ex);
            }

            $this->lastProcessedEventNumber = $e->originalEventNumber();
            $processed = true;
        }

        if ($this->verbose) {
            /** @psalm-suppress PossiblyNullReference */
            $this->log->debug(\sprintf(
                'Catch-up Subscription %s to %s: %s event (%s, %d, %s @ %d)',
                $this->subscriptionName(),
                $this->isSubscribedToAll() ? self::AllStream : $this->streamId(),
                $processed ? 'processed' : 'skipping',
                $e->originalEvent()->eventStreamId(),
                $e->originalEvent()->eventNumber(),
                $e->originalEvent()->eventType(),
                $e->originalEventNumber()
            ));
        }
    }
}
