<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2022 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2022 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\Message;

use Prooph\EventStoreClient\SystemData\TcpPackage;
use Prooph\EventStoreClient\Transport\Tcp\TcpPackageConnection;

/** @internal */
class HandleTcpPackageMessage implements Message
{
    private TcpPackageConnection $tcpPackageConnection;
    private TcpPackage $tcpPackage;

    public function __construct(TcpPackageConnection $tcpPackageConnection, TcpPackage $tcpPackage)
    {
        $this->tcpPackageConnection = $tcpPackageConnection;
        $this->tcpPackage = $tcpPackage;
    }

    /** @psalm-pure */
    public function tcpPackageConnection(): TcpPackageConnection
    {
        return $this->tcpPackageConnection;
    }

    /** @psalm-pure */
    public function tcpPackage(): TcpPackage
    {
        return $this->tcpPackage;
    }

    /** @psalm-pure */
    public function __toString(): string
    {
        return 'HandleTcpPackageMessage';
    }
}
