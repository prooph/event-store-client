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

use Amp\Promise;
use Amp\Success;
use Prooph\EventStoreClient\IpEndPoint;

/** @internal */
final class StaticEndPointDiscoverer implements EndPointDiscoverer
{
    /** @var Promise */
    private $promise;

    public function __construct(IpEndPoint $endPoint, bool $isSsl)
    {
        $this->promise = new Success(
            new NodeEndPoints($isSsl ? null : $endPoint, $isSsl ? $endPoint : null)
        );
    }

    public function discoverAsync(?IpEndPoint $failedTcpEndPoint): Promise
    {
        return $this->promise;
    }
}
