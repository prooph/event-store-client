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

use Closure;
use Prooph\EventStore\Exception\ConnectionClosed;
use Prooph\EventStore\Exception\OperationTimedOut;
use Prooph\EventStore\Exception\RetriesLimitReached;
use Prooph\EventStore\Util\DateTime;
use Prooph\EventStore\Util\Guid;
use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStoreClient\Transport\Tcp\TcpPackageConnection;
use SplQueue;

/** @internal */
class OperationsManager
{
    private Closure $operationItemSeqNoComparer;
    private string $connectionName;
    private ConnectionSettings $settings;
    /** @var OperationItem[] */
    private array $activeOperations = [];
    /** @var SplQueue<OperationItem> */
    private SplQueue $waitingOperations;
    /** @var OperationItem[] */
    private array $retryPendingOperations = [];
    private int $totalOperationCount = 0;

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
        $closedConnectionException = ConnectionClosed::withName($this->connectionName);

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
                    $operation->operation()->fail(new OperationTimedOut($err));
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
                $operation->setCorrelationId(Guid::generateAsHex());
                $operation->incRetryCount();
                $this->logDebug('retrying, old corrId %s, operation %s', $oldCorrId, (string) $operation);
                $this->scheduleOperation($operation, $connection);
            }

            $this->retryPendingOperations = [];
        }

        $this->tryScheduleWaitingOperations($connection);
    }

    public function scheduleOperationRetry(OperationItem $operation): void
    {
        if (! $this->removeOperation($operation)) {
            return;
        }

        $this->logDebug('ScheduleOperationRetry for %s', (string) $operation);
        if ($operation->maxRetries() >= 0 && $operation->retryCount() >= $operation->maxRetries()) {
            $operation->operation()->fail(
                RetriesLimitReached::with($operation->retryCount())
            );

            return;
        }

        $this->retryPendingOperations[] = $operation;
    }

    public function removeOperation(OperationItem $operation): bool
    {
        if (! isset($this->activeOperations[$operation->correlationId()])) {
            $this->logDebug('RemoveOperation FAILED for %s', (string) $operation);

            return false;
        }

        unset($this->activeOperations[$operation->correlationId()]);
        $this->logDebug('RemoveOperation SUCCEEDED for %s', (string) $operation);

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
            (string) $package->command(),
            $package->correlationId(),
            (string) $operation
        );
        $connection->enqueueSend($package);
    }

    public function enqueueOperation(OperationItem $operation): void
    {
        $this->logDebug('EnqueueOperation WAITING for %s', (string) $operation);
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
