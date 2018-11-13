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

use Amp\Promise;
use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStoreClient\Exception\ConnectionClosedException;
use Prooph\EventStoreClient\Exception\OperationTimedOutException;
use Prooph\EventStoreClient\Exception\RetriesLimitReachedException;
use Prooph\EventStoreClient\Transport\Tcp\TcpPackageConnection;
use Prooph\EventStoreClient\Util\DateTime;
use Prooph\EventStoreClient\Util\UuidGenerator;
use SplQueue;

/** @internal */
class OperationsManager
{
    /** @var callable */
    private $operationItemSeqNoComparer;
    /** @var string */
    private $connectionName;
    /** @var ConnectionSettings */
    private $settings;
    /** @var OperationItem[] */
    private $activeOperations = [];
    /** @var SplQueue<OperationItem> */
    private $waitingOperations;
    /** @var OperationItem[] */
    private $retryPendingOperations = [];
    /** @var int */
    private $totalOperationCount = 0;

    public function __construct(string $connectionName, ConnectionSettings $settings)
    {
        $this->connectionName = $connectionName;
        $this->settings = $settings;

        $this->operationItemSeqNoComparer = function (OperationItem $a, OperationItem $b): int {
            if ($a->segNo() === $b->segNo()) {
                return 0;
            }

            return ($a->segNo() < $b->segNo()) ? -1 : 1;
        };

        $this->waitingOperations = new SplQueue();
    }

    public function totalOperationCount(): int
    {
        return $this->totalOperationCount;
    }

    public function getActiveOperation(string $correlationId): ?OperationItem
    {
        return $this->activeOperations[$correlationId] ?? null;
    }

    public function cleanUp(): void
    {
        $closedConnectionException = ConnectionClosedException::withName($this->connectionName);

        foreach ($this->activeOperations as $operationItem) {
            try {
                $operationItem->operation()->fail($closedConnectionException);
            } catch (\Error $e) {
                // ignore, promise was already resolved
            }
        }

        while (! $this->waitingOperations->isEmpty()) {
            $operationItem = $this->waitingOperations->dequeue();
            try {
                $operationItem->operation()->fail($closedConnectionException);
            } catch (\Error $e) {
                // ignore, promise was already resolved
            }
        }

        foreach ($this->retryPendingOperations as $operationItem) {
            try {
                $operationItem->operation()->fail($closedConnectionException);
            } catch (\Error $e) {
                // ignore, promise was already resolved
            }
        }

        $this->activeOperations = [];
        $this->retryPendingOperations = [];
        $this->totalOperationCount = 0;
    }

    public function checkTimeoutsAndRetry(TcpPackageConnection $connection): void
    {
        $retryOperations = [];
        $removeOperations = [];

        foreach ($this->activeOperations as $operation) {
            if ($operation->connectionId() !== $connection->connectionId()) {
                $retryOperations[] = $operation;
            } elseif ($operation->timeout() > 0
                && (float) DateTime::utcNow()->format('U.u') - (float) $operation->lastUpdated()->format('U.u') > $this->settings->operationTimeout()
            ) {
                $err = \sprintf(
                    'EventStoreNodeConnection \'%s\': subscription never got confirmation from server',
                    $connection->connectionId()
                );

                $this->settings->log()->error($err);

                if ($this->settings->failOnNoServerResponse()) {
                    $operation->operation()->fail(new OperationTimedOutException($err));
                    $removeOperations[] = $operation;
                } else {
                    $retryOperations[] = $operation;
                }
            }
        }

        foreach ($retryOperations as $operation) {
            $this->scheduleOperationRetry($operation);
        }

        foreach ($removeOperations as $operation) {
            $this->removeOperation($operation);
        }

        if (\count($this->retryPendingOperations) > 0) {
            \usort($this->retryPendingOperations, $this->operationItemSeqNoComparer);

            foreach ($this->retryPendingOperations as $operation) {
                $oldCorrId = $operation->correlationId();
                $operation->setCorrelationId(UuidGenerator::generate());
                $operation->incRetryCount();
                $this->logDebug('retrying, old corrId %s, operation %s', $oldCorrId, $operation);
                $this->scheduleOperation($operation, $connection);
            }
        }

        $this->tryScheduleWaitingOperations($connection);
    }

    public function scheduleOperationRetry(OperationItem $operation): void
    {
        if (! $this->removeOperation($operation)) {
            return;
        }

        $this->logDebug('ScheduleOperationRetry for %s', $operation);
        if ($operation->maxRetries() >= 0 && $operation->retryCount() >= $operation->maxRetries()) {
            $operation->operation()->fail(
                RetriesLimitReachedException::with($operation->retryCount())
            );

            return;
        }

        $this->retryPendingOperations[] = $operation;
    }

    public function removeOperation(OperationItem $operation): bool
    {
        if (! isset($this->activeOperations[$operation->correlationId()])) {
            $this->logDebug('RemoveOperation FAILED for %s', $operation);

            return false;
        }

        unset($this->activeOperations[$operation->correlationId()]);
        $this->logDebug('RemoveOperation SUCCEEDED for %s', $operation);

        return true;
    }

    public function tryScheduleWaitingOperations(TcpPackageConnection $connection): void
    {
        while (! $this->waitingOperations->isEmpty()
            && \count($this->activeOperations) < $this->settings->maxConcurrentItems()
        ) {
            $this->executeOperation($this->waitingOperations->dequeue(), $connection);
        }

        $this->totalOperationCount = \count($this->activeOperations) + \count($this->waitingOperations);
    }

    public function executeOperation(OperationItem $operation, TcpPackageConnection $connection): void
    {
        $operation->setConnectionId($connection->connectionId());
        $operation->setLastUpdated(DateTime::utcNow());

        $correlationId = $operation->correlationId();
        $this->activeOperations[$correlationId] = $operation;

        $package = $operation->operation()->createNetworkPackage($correlationId);

        $this->logDebug('ExecuteOperation package %s, %s, %s',
            $package->command(),
            $package->correlationId(),
            $operation
        );
        $connection->enqueueSend($package);
    }

    public function enqueueOperation(OperationItem $operation): void
    {
        $this->logDebug('EnqueueOperation WAITING for %s', $operation);
        $this->waitingOperations->enqueue($operation);
    }

    public function scheduleOperation(OperationItem $operation, TcpPackageConnection $connection): void
    {
        $this->waitingOperations->enqueue($operation);
        $this->tryScheduleWaitingOperations($connection);
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
