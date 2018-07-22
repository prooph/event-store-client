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

namespace Prooph\EventStoreClient\SystemData;

use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\IpEndPoint;

/** @internal */
class InspectionResult
{
    /** @var InspectionDecision */
    private $inspectionDecision;
    /** @var string */
    private $description;
    /** @var IpEndPoint|null */
    private $tcpEndPoint;
    /** @var IpEndPoint|null */
    private $secureTcpEndPoint;

    public function __construct(
        InspectionDecision $decision,
        string $description,
        IpEndPoint $tcpEndPoint = null,
        IpEndPoint $secureTcpEndPoint = null)
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

    public function decision(): InspectionDecision
    {
        return $this->inspectionDecision;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function tcpEndPoint(): ?IpEndPoint
    {
        return $this->tcpEndPoint;
    }

    public function secureTcpEndPoint(): ?IpEndPoint
    {
        return $this->secureTcpEndPoint;
    }
}
