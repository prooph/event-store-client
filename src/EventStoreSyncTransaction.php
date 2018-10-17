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

namespace Prooph\EventStoreClient;

use Prooph\EventStoreClient\Internal\EventStoreSyncTransactionConnection;

class EventStoreSyncTransaction
{
    /** @var int */
    private $transactionId;
    /** @var UserCredentials|null */
    private $userCredentials;
    /** @var EventStoreSyncTransactionConnection */
    private $connection;
    /** @var bool */
    private $isRolledBack;
    /** @var bool */
    private $isCommitted;

    /** @internal */
    public function __construct(
        int $transactionId,
        ?UserCredentials $userCredentials,
        EventStoreSyncTransactionConnection $connection
    ) {
        $this->transactionId = $transactionId;
        $this->userCredentials = $userCredentials;
        $this->connection = $connection;
    }

    public function transactionId(): int
    {
        return $this->transactionId;
    }

    public function commit(): WriteResult
    {
        if ($this->isRolledBack) {
            throw new \RuntimeException('Cannot commit a rolledback transaction');
        }

        if ($this->isCommitted) {
            throw new \RuntimeException('Transaction is already committed');
        }

        return $this->connection->commitTransaction($this, $this->userCredentials);
    }

    /**
     * @param EventData[] $events
     * @return void
     */
    public function write(array $events): void
    {
        if ($this->isRolledBack) {
            throw new \RuntimeException('Cannot commit a rolledback transaction');
        }

        if ($this->isCommitted) {
            throw new \RuntimeException('Transaction is already committed');
        }

        $this->connection->transactionalWrite($this, $events, $this->userCredentials);
    }

    public function rollback(): void
    {
        if ($this->isCommitted) {
            throw new \RuntimeException('Transaction is already committed');
        }

        $this->isRolledBack = true;
    }
}
