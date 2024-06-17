<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2024 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2024 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\Message;

use Prooph\EventStoreClient\SystemData\TcpPackage;
use Prooph\EventStoreClient\Transport\Tcp\TcpPackageConnection;

/**
 * @internal
 *
 * @psalm-immutable
 */
class HandleTcpPackageMessage implements Message
{
    public function __construct(
        private readonly TcpPackageConnection $tcpPackageConnection,
        private readonly TcpPackage $tcpPackage
    ) {
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
