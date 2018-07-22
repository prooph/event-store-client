<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Prooph\EventStoreClient\Internal\EventStoreAsyncNodeConnection;
use Throwable;

class ClientErrorEventArgs implements EventArgs
{
    /** @var EventStoreAsyncNodeConnection */
    private $connection;
    /** @var Throwable */
    private $exception;

    public function __construct(EventStoreAsyncNodeConnection $connection, Throwable $exception)
    {
        $this->connection = $connection;
        $this->exception = $exception;
    }

    public function connection(): EventStoreAsyncNodeConnection
    {
        return $this->connection;
    }

    public function exception(): Throwable
    {
        return $this->exception;
    }
}
