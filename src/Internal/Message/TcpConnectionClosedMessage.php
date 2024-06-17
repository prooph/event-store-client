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

use Prooph\EventStoreClient\Transport\Tcp\TcpPackageConnection;
use Throwable;

/** @internal */
class TcpConnectionClosedMessage implements Message
{
    public function __construct(
        private readonly TcpPackageConnection $tcpPackageConnection,
        private readonly ?Throwable $exception = null
    ) {
    }

    public function tcpPackageConnection(): TcpPackageConnection
    {
        return $this->tcpPackageConnection;
    }

    public function exception(): ?Throwable
    {
        return $this->exception;
    }

    public function __toString(): string
    {
        return 'TcpConnectionClosedMessage';
    }
}
