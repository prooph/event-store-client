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

use function Amp\call;
use Amp\Delayed;
use Amp\Promise;
use Prooph\EventStore\AllEventsSlice;
use Prooph\EventStore\Async\CatchUpSubscriptionDropped;
use Prooph\EventStore\Async\EventAppearedOnCatchupSubscription;
use Prooph\EventStore\Async\EventStoreAllCatchUpSubscription as AsyncEventStoreAllCatchUpSubscription;
use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\Async\LiveProcessingStartedOnCatchUpSubscription;
use Prooph\EventStore\CatchUpSubscriptionSettings;
use Prooph\EventStore\Position;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SubscriptionDropReason;
use Prooph\EventStore\UserCredentials;
use Psr\Log\LoggerInterface as Logger;
use Throwable;

class EventStoreAllCatchUpSubscription extends EventStoreCatchUpSubscription implements AsyncEventStoreAllCatchUpSubscription
{
    private Position $nextReadPosition;
    private Position $lastProcessedPosition;

    /**
     * @internal
     */
    public function __construct(
        EventStoreConnection $connection,
        Logger $logger,
        ?Position $fromPositionExclusive, // if null from the very beginning
        ?UserCredentials $userCredentials,
        EventAppearedOnCatchupSubscription $eventAppeared,
        ?LiveProcessingStartedOnCatchUpSubscription $liveProcessingStarted,
        ?CatchUpSubscriptionDropped $subscriptionDropped,
        CatchUpSubscriptionSettings $settings
    ) {
        parent::__construct(
            $connection,
            $logger,
            '',
            $userCredentials,
            $eventAppeared,
            $liveProcessingStarted,
            $subscriptionDropped,
            $settings
        );

        $this->lastProcessedPosition = $fromPositionExclusive ?? Position::end();
        $this->nextReadPosition = $fromPositionExclusive ?? Position::start();
    }

    public function lastProcessedPosition(): Position
    {
        return $this->lastProcessedPosition;
    }

    protected function readEventsTillAsync(
        EventStoreConnection $connection,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials,
        ?int $lastCommitPosition,
        ?int $lastEventNumber
    ): Promise {
        return $this->readEventsInternalAsync($connection, $resolveLinkTos, $userCredentials, $lastCommitPosition);
    }

    private function readEventsInternalAsync(
        EventStoreConnection $connection,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials,
        ?int $lastCommitPosition
    ): Promise {
        return call(function () use ($connection, $resolveLinkTos, $userCredentials, $lastCommitPosition): \Generator {
            do {
                $slice = yield $connection->readAllEventsForwardAsync(
                    $this->nextReadPosition,
                    $this->readBatchSize,
                    $resolveLinkTos,
                    $userCredentials
                );

                $shouldStopOrDone = yield $this->readEventsCallbackAsync($slice, $lastCommitPosition);
            } while (! $shouldStopOrDone);
        });
    }

    /** @return Promise<bool> */
    private function readEventsCallbackAsync(AllEventsSlice $slice, ?int $lastCommitPosition): Promise
    {
        return call(function () use ($slice, $lastCommitPosition): \Generator {
            $shouldStopOrDone = $this->shouldStop || yield $this->processEventsAsync($lastCommitPosition, $slice);

            if ($shouldStopOrDone && $this->verbose) {
                $this->log->debug(\sprintf(
                    'Catch-up Subscription %s to %s: finished reading events, nextReadPosition = %s',
                    $this->subscriptionName(),
                    $this->isSubscribedToAll() ? '<all>' : $this->streamId(),
                    $this->nextReadPosition ? $this->nextReadPosition->__toString() : '<null>'
                ));
            }

            return $shouldStopOrDone;
        });
    }

    /** @return Promise<bool> */
    private function processEventsAsync(?int $lastCommitPosition, AllEventsSlice $slice): Promise
    {
        return call(function () use ($lastCommitPosition, $slice): \Generator {
            foreach ($slice->events() as $e) {
                if (null === $e->originalPosition()) {
                    throw new \Exception(\sprintf(
                        'Subscription %s event came up with no OriginalPosition',
                        $this->subscriptionName()
                    ));
                }

                yield $this->tryProcessAsync($e);
            }

            $this->nextReadPosition = $slice->nextPosition();

            $done = (null === $lastCommitPosition)
                ? $slice->isEndOfStream()
                : $slice->nextPosition()->greaterOrEquals(new Position($lastCommitPosition, $lastCommitPosition));

            if (! $done && $slice->isEndOfStream()) {
                // we are waiting for server to flush its data
                yield new Delayed(1000);
            }

            return $done;
        });
    }

    protected function tryProcessAsync(ResolvedEvent $e): Promise
    {
        return call(function () use ($e): \Generator {
            $processed = false;

            if ($e->originalPosition()->greater($this->lastProcessedPosition)) {
                try {
                    yield ($this->eventAppeared)($this, $e);
                } catch (Throwable $ex) {
                    $this->dropSubscription(SubscriptionDropReason::eventHandlerException(), $ex);
                }

                $this->lastProcessedPosition = $e->originalPosition();
                $processed = true;
            }

            if ($this->verbose) {
                $this->log->debug(\sprintf(
                    'Catch-up Subscription %s to %s: %s event (%s, %d, %s @ %s)',
                    $this->subscriptionName(),
                    $this->isSubscribedToAll() ? '<all>' : $this->streamId(),
                    $processed ? 'processed' : 'skipping',
                    $e->originalEvent()->eventStreamId(),
                    $e->originalEvent()->eventNumber(),
                    $e->originalEvent()->eventType(),
                    $e->originalPosition() ? $e->originalPosition()->__toString() : '<null>'
                ));
            }
        });
    }
}
