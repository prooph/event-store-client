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

namespace Prooph\EventStoreClient\Internal\Message;

use Prooph\EventStoreClient\Transport\Tcp\TcpPackageConnection;

/** @internal */
class TcpConnectionEstablishedMessage implements Message
{
    private TcpPackageConnection $tcpPackageConnection;

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
