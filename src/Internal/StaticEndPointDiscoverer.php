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

use Amp\Promise;
use Amp\Success;
use Prooph\EventStore\EndPoint;

/** @internal */
final class StaticEndPointDiscoverer implements EndPointDiscoverer
{
    private Promise $promise;

    public function __construct(EndPoint $endPoint, bool $isSsl)
    {
        $this->promise = new Success(
            new NodeEndPoints($isSsl ? null : $endPoint, $isSsl ? $endPoint : null)
        );
    }

    public function discoverAsync(?EndPoint $failedTcpEndPoint): Promise
    {
        return $this->promise;
    }
}
