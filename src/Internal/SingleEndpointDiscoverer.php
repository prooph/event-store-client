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

namespace Prooph\EventStoreClient\Internal;

use Prooph\EventStore\EndPoint;
use Prooph\EventStoreClient\Uri;

/** @internal */
final class SingleEndpointDiscoverer implements EndPointDiscoverer
{
    public function __construct(
        private readonly Uri $uri,
        private readonly bool $useSslConnection
    ) {
    }

    public function discover(?EndPoint $failedTcpEndPoint): NodeEndPoints
    {
        $endPoint = new EndPoint($this->uri->host(), $this->uri->port());

        return new NodeEndPoints(
            $this->useSslConnection ? null : $endPoint,
            $this->useSslConnection ? $endPoint : null
        );
    }
}
