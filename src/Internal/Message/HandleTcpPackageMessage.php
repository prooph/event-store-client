<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\Message;

use Prooph\EventStoreClient\SystemData\TcpPackage;
use Prooph\EventStoreClient\Transport\Tcp\TcpPackageConnection;

/** @internal */
class HandleTcpPackageMessage implements Message
{
    /** @var TcpPackageConnection */
    private $tcpPackageConnection;
    /** @var TcpPackage */
    private $tcpPackage;

    public function __construct(TcpPackageConnection $tcpPackageConnection, TcpPackage $tcpPackage)
    {
        $this->tcpPackageConnection = $tcpPackageConnection;
        $this->tcpPackage = $tcpPackage;
    }

    public function tcpPackageConnection(): TcpPackageConnection
    {
        return $this->tcpPackageConnection;
    }

    public function tcpPackage(): TcpPackage
    {
        return $this->tcpPackage;
    }

    public function __toString(): string
    {
        return 'HandleTcpPackageMessage';
    }
}
