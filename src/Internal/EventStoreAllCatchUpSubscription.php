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

use Amp\Delayed;
use Amp\Promise;
use Prooph\EventStoreClient\AllEventsSlice;
use Prooph\EventStoreClient\CatchUpSubscriptionSettings;
use Prooph\EventStoreClient\EventStoreAsyncConnection;
use Prooph\EventStoreClient\Exception\RuntimeException;
use Prooph\EventStoreClient\Position;
use Prooph\EventStoreClient\ResolvedEvent;
use Prooph\EventStoreClient\SubscriptionDropReason;
use Prooph\EventStoreClient\UserCredentials;
use Psr\Log\LoggerInterface as Logger;
use function Amp\call;

class EventStoreAllCatchUpSubscription extends EventStoreCatchUpSubscription
{
    /** @var Position */
    private $nextReadPosition;
    /** @var Position */
    private $lastProcessedPosition;

    /**
     * @internal
     *
     * @param EventStoreAsyncConnection $connection
     * @param Logger $logger
     * @param Position|null $fromPositionExclusive
     * @param null|UserCredentials $userCredentials
     * @param callable(\Prooph\EventStoreClient\EventStoreSubscription $subscription, \Prooph\EventStoreClient\Internal\ResolvedEvent $resolvedEvent): Promise $eventAppeared
     * @param null|callable(\Prooph\EventStoreClient\Internal\EventStoreCatchUpSubscription $subscription): void $liveProcessingStarted
     * @param null|callable(\Prooph\EventStoreClient\EventStoreSubscription $subscription, \Prooph\EventStoreClient\SubscriptionDropReason $reason, \Throwable $exception): void $subscriptionDropped
     * @param CatchUpSubscriptionSettings $settings
     */
    public function __construct(
        EventStoreAsyncConnection $connection,
        Logger $logger,
        ?Position $fromPositionExclusive, // if null from the very beginning
        ?UserCredentials $userCredentials,
        callable $eventAppeared,
        ?callable $liveProcessingStarted,
        ?callable $subscriptionDropped,
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
        EventStoreAsyncConnection $connection,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials,
        ?int $lastCommitPosition,
        ?int $lastEventNumber
    ): Promise {
        return $this->readEventsInternalAsync($connection, $resolveLinkTos, $userCredentials, $lastCommitPosition);
    }

    private function readEventsInternalAsync(
        EventStoreAsyncConnection $connection,
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

    /**
     * @return Promise<bool>
     */
    private function readEventsCallbackAsync(AllEventsSlice $slice, ?int $lastCommitPosition): Promise
    {
        return call(function () use ($slice, $lastCommitPosition): \Generator {
            $shouldStopOrDone = $this->shouldStop || yield $this->processEventsAsync($lastCommitPosition, $slice);

            if ($shouldStopOrDone && $this->verbose) {
                $this->log->debug(\sprintf(
                    'Catch-up Subscription %s to %s: finished reading events, nextReadPosition = %s',
                    $this->subscriptionName(),
                    $this->isSubscribedToAll() ? '<all>' : $this->streamId(),
                    $this->nextReadPosition ?? '<null>'
                ));
            }

            return $shouldStopOrDone;
        });
    }

    /**
     * @return Promise<bool>
     */
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
                yield new Delayed(1000); // we are waiting for server to flush its data
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
                    $promise = ($this->eventAppeared)($this, $e);

                    if (! $promise instanceof Promise) {
                        throw new RuntimeException('Event appeared callback needs to return an ' . Promise::class);
                    }

                    yield $promise;
                } catch (\Throwable $ex) {
                    $this->dropSubscription(SubscriptionDropReason::eventHandlerException(), $ex);

                    throw $ex;
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
                    $e->originalPosition() ?? '<null>'
                ));
            }
        });
    }
}
