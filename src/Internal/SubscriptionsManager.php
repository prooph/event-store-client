<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStoreClient\Exception\ConnectionClosedException;
use Prooph\EventStoreClient\Exception\OperationTimedOutException;
use Prooph\EventStoreClient\Exception\RetriesLimitReachedException;
use Prooph\EventStoreClient\SubscriptionDropReason;
use Prooph\EventStoreClient\Transport\Tcp\TcpPackageConnection;
use Prooph\EventStoreClient\Util\DateTime;
use Prooph\EventStoreClient\Util\UuidGenerator;
use SplQueue;

/** @internal  */
class SubscriptionsManager
{
    /** @var string */
    private $connectionName;
    /** @var ConnectionSettings */
    private $settings;
    /** @var SubscriptionItem[] */
    private $activeSubscriptions = [];
    /** @var SplQueue */
    private $waitingSubscriptions;
    /** @var SubscriptionItem[] */
    private $retryPendingSubscriptions = [];

    public function __construct(string $connectionName, ConnectionSettings $settings)
    {
        $this->connectionName = $connectionName;
        $this->settings = $settings;
        $this->waitingSubscriptions = new SplQueue();
    }

    public function getActiveSubscription(string $correlationId): ?SubscriptionItem
    {
        return $this->activeSubscriptions[$correlationId] ?? null;
    }

    public function cleanUp(): void
    {
        $connectionClosedException = ConnectionClosedException::withName($this->connectionName);

        foreach ($this->activeSubscriptions as $subscriptionItem) {
            $subscriptionItem->operation()->dropSubscription(
                SubscriptionDropReason::connectionClosed(),
                $connectionClosedException
            );
        }

        while (! $this->waitingSubscriptions->isEmpty()) {
            $subscriptionItem = $this->waitingSubscriptions->dequeue();
            \assert($subscriptionItem instanceof SubscriptionItem);
            $subscriptionItem->operation()->dropSubscription(
                SubscriptionDropReason::connectionClosed(),
                $connectionClosedException
            );
        }

        foreach ($this->retryPendingSubscriptions as $subscriptionItem) {
            $subscriptionItem->operation()->dropSubscription(
                SubscriptionDropReason::connectionClosed(),
                $connectionClosedException
            );
        }

        $this->activeSubscriptions = [];
        $this->retryPendingSubscriptions = [];
    }

    public function purgeSubscribedAndDroppedSubscriptions(string $connectionId): void
    {
        $subscriptionsToRemove = new SplQueue();

        foreach ($this->activeSubscriptions as $subscriptionItem) {
            if ($subscriptionItem->connectionId() !== $connectionId) {
                continue;
            }

            $subscriptionItem->operation()->connectionClosed();
            $subscriptionsToRemove->enqueue($subscriptionItem);
        }

        while (! $subscriptionsToRemove->isEmpty()) {
            $subscriptionItem = $subscriptionsToRemove->dequeue();
            \assert($subscriptionItem instanceof SubscriptionItem);
            unset($this->activeSubscriptions[$subscriptionItem->correlationId()]);
        }
    }

    public function checkTimeoutsAndRetry(TcpPackageConnection $connection): void
    {
        $retrySubscriptions = new SplQueue();
        $removeSubscriptions = new SplQueue();

        foreach ($this->activeSubscriptions as $subscription) {
            if ($subscription->isSubscribed()) {
                continue;
            }

            if ($subscription->connectionId() !== $connection->connectionId()) {
                $this->retryPendingSubscriptions[] = $subscription;
            } elseif ($subscription->timeout() > 0
                && (float) DateTime::utcNow()->format('U.u') - (float) $subscription->lastUpdated()->format('U.u') > $this->settings->operationTimeout()
            ) {
                $err = \sprintf(
                    'EventStoreNodeConnection \'%s\': subscription never got confirmation from server',
                    $connection->connectionId()
                );

                $this->settings->log()->error($err);

                if ($this->settings->failOnNoServerResponse()) {
                    $subscription->operation()->dropSubscription(
                        SubscriptionDropReason::subscribingError(),
                        new OperationTimedOutException($err)
                    );
                    $removeSubscriptions->enqueue($subscription);
                } else {
                    $retrySubscriptions->enqueue($subscription);
                }
            }
        }

        while (! $retrySubscriptions->isEmpty()) {
            $this->scheduleSubscriptionRetry($retrySubscriptions->dequeue());
        }

        while (! $removeSubscriptions->isEmpty()) {
            $this->removeSubscription($removeSubscriptions->dequeue());
        }

        if (\count($this->retryPendingSubscriptions) > 0) {
            foreach ($this->retryPendingSubscriptions as $subscription) {
                $subscription->incRetryCount();
                $this->startSubscription($subscription, $connection);
            }

            $this->retryPendingSubscriptions = [];
        }

        while (! $this->waitingSubscriptions->isEmpty()) {
            $this->startSubscription($this->waitingSubscriptions->dequeue(), $connection);
        }
    }

    public function removeSubscription(SubscriptionItem $subscription): bool
    {
        $result = isset($this->activeSubscriptions[$subscription->correlationId()]);
        $this->logDebug('RemoveSubscription %s, result %s', $subscription, $result ? 'yes' : 'no');
        unset($this->activeSubscriptions[$subscription->correlationId()]);

        return $result;
    }

    public function scheduleSubscriptionRetry(SubscriptionItem $subscription): void
    {
        if (! $this->removeSubscription($subscription)) {
            $this->logDebug('RemoveSubscription failed when trying to retry %s', $subscription);

            return;
        }

        if ($subscription->maxRetries() >= 0 && $subscription->retryCount() >= $subscription->maxRetries()) {
            $this->logDebug('RETRIES LIMIT REACHED when trying to retry %s', $subscription);
            $subscription->operation()->dropSubscription(
                SubscriptionDropReason::subscribingError(),
                RetriesLimitReachedException::with($subscription->retryCount())
            );

            return;
        }

        $this->logDebug('retrying subscription %s', $subscription);
        $this->retryPendingSubscriptions[] = $subscription;
    }

    public function enqueueSubscription(SubscriptionItem $subscriptionItem): void
    {
        $this->waitingSubscriptions->enqueue($subscriptionItem);
    }

    public function startSubscription(SubscriptionItem $subscription, TcpPackageConnection $connection): void
    {
        if ($subscription->isSubscribed()) {
            $this->logDebug('StartSubscription REMOVING due to already subscribed %s', $subscription);
            $this->removeSubscription($subscription);

            return;
        }

        $correlationId = UuidGenerator::generateWithoutDash();
        $subscription->setCorrelationId($correlationId);
        $subscription->setConnectionId($connection->connectionId());
        $subscription->setLastUpdated(DateTime::utcNow());

        $this->activeSubscriptions[$correlationId] = $subscription;

        if (! $subscription->operation()->subscribe($correlationId, $connection)) {
            $this->logDebug('StartSubscription REMOVING AS COULD NOT SUBSCRIBE %s', $subscription);
            $this->removeSubscription($subscription);
        }
        $this->logDebug('StartSubscription SUBSCRIBING %s', $subscription);
    }

    private function logDebug(string $message, ...$parameters): void
    {
        if ($this->settings->verboseLogging()) {
            $message = empty($parameters)
                ? $message
                : \sprintf($message, ...$parameters);

            $this->settings->log()->debug(\sprintf(
                'EventStoreNodeConnection \'%s\': %s',
                $this->connectionName,
                $message
            ));
        }
    }
}
