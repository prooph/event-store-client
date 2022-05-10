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

namespace Prooph\EventStoreClient\SystemData;

use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Exception\InvalidArgumentException;

/**
 * @internal
 *
 * @psalm-immutable
 */
class InspectionResult
{
    private InspectionDecision $inspectionDecision;
    private string $description;
    private ?EndPoint $tcpEndPoint;
    private ?EndPoint $secureTcpEndPoint;

    public function __construct(
        InspectionDecision $decision,
        string $description,
        ?EndPoint $tcpEndPoint = null,
        ?EndPoint $secureTcpEndPoint = null)
    {
        if ($decision->equals(InspectionDecision::reconnect())) {
            if (null === $tcpEndPoint) {
                throw new InvalidArgumentException('TcpEndPoint is null for reconnect');
            }
        } elseif (null !== $tcpEndPoint) {
            throw new InvalidArgumentException('TcpEndPoint is not null for decision ' . $decision->name());
        }

        $this->inspectionDecision = $decision;
        $this->description = $description;
        $this->tcpEndPoint = $tcpEndPoint;
        $this->secureTcpEndPoint = $secureTcpEndPoint;
    }

    /** @psalm-pure */
    public function decision(): InspectionDecision
    {
        return $this->inspectionDecision;
    }

    /** @psalm-pure */
    public function description(): string
    {
        return $this->description;
    }

    /** @psalm-pure */
    public function tcpEndPoint(): ?EndPoint
    {
        return $this->tcpEndPoint;
    }

    /** @psalm-pure */
    public function secureTcpEndPoint(): ?EndPoint
    {
        return $this->secureTcpEndPoint;
    }
}
