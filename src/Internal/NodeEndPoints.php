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

namespace Prooph\EventStoreClient\Internal;

use Prooph\EventStoreClient\EndPoint;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;

/** @internal */
class NodeEndPoints
{
    /** @var EndPoint|null */
    private $tcpEndPoint;
    /** @var EndPoint|null */
    private $secureTcpEndPoint;

    public function __construct(?EndPoint $tcpEndPoint, ?EndPoint $secureTcpEndPoint = null)
    {
        if (($tcpEndPoint && $secureTcpEndPoint) === null) {
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
