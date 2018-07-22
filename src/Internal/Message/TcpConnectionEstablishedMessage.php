<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\Message;

use Prooph\EventStoreClient\Transport\Tcp\TcpPackageConnection;

/** @internal */
class TcpConnectionEstablishedMessage implements Message
{
    /** @var TcpPackageConnection */
    private $tcpPackageConnection;

    public function __construct(TcpPackageConnection $tcpPackageConnection)
    {
        $this->tcpPackageConnection = $tcpPackageConnection;
    }

    public function tcpPackageConnection(): TcpPackageConnection
    {
        return $this->tcpPackageConnection;
    }

    public function __toString(): string
    {
        return 'TcpConnectionEstablishedMessage';
    }
}
