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
use Throwable;

/** @internal */
class TcpConnectionErrorMessage implements Message
{
    private TcpPackageConnection $tcpPackageConnection;
    private Throwable $exception;

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
