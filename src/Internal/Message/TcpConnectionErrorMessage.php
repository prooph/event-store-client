<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\Message;

use Prooph\EventStoreClient\Transport\Tcp\TcpPackageConnection;
use Throwable;

/** @internal */
class TcpConnectionErrorMessage implements Message
{
    /** @var TcpPackageConnection */
    private $tcpPackageConnection;
    /** @var Throwable */
    private $exception;

    public function __construct(TcpPackageConnection $tcpPackageConnection, Throwable $exception)
    {
        $this->tcpPackageConnection = $tcpPackageConnection;
        $this->exception = $exception;
    }

    public function tcpPackageConnection(): TcpPackageConnection
    {
        return $this->tcpPackageConnection;
    }

    public function exception(): Throwable
    {
        return $this->exception;
    }

    public function __toString(): string
    {
        return 'TcpConnectionErrorMessage';
    }
}
