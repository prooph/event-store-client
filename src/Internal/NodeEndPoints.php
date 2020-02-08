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

namespace Prooph\EventStoreClient\Internal;

use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Exception\InvalidArgumentException;

/** @internal */
class NodeEndPoints
{
    private ?EndPoint $tcpEndPoint;
    private ?EndPoint $secureTcpEndPoint;

    public function __construct(?EndPoint $tcpEndPoint, ?EndPoint $secureTcpEndPoint = null)
    {
        if (null === $tcpEndPoint && null === $secureTcpEndPoint) {
            throw new InvalidArgumentException('Both endpoints are null');
        }

        $this->tcpEndPoint = $tcpEndPoint;
        $this->secureTcpEndPoint = $secureTcpEndPoint;
    }

    public function tcpEndPoint(): ?EndPoint
    {
        return $this->tcpEndPoint;
    }

    public function secureTcpEndPoint(): ?EndPoint
    {
        return $this->secureTcpEndPoint;
    }

    public function __toString(): string
    {
        return \sprintf('[%s, %s]',
            null === $this->tcpEndPoint ? 'n/a' : $this->tcpEndPoint->__toString(),
            null === $this->secureTcpEndPoint ? 'n/a' : $this->secureTcpEndPoint->__toString()
        );
    }
}
