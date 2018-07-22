<?php
/**
 * This file is part of the prooph/event-store-client.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
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
