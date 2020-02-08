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
use Prooph\EventStoreClient\Uri;

/** @internal */
final class SingleEndpointDiscoverer implements EndPointDiscoverer
{
    private Uri $uri;
    private bool $useSslConnection;

    public function __construct(Uri $uri, bool $useSslConnection)
    {
        $this->uri = $uri;
        $this->useSslConnection = $useSslConnection;
    }

    public function discoverAsync(?EndPoint $failedTcpEndPoint): Promise
    {
        $endPoint = new EndPoint($this->uri->host(), $this->uri->port());

        return new Success(
            new NodeEndPoints(
                $this->useSslConnection ? null : $endPoint,
                $this->useSslConnection ? $endPoint : null
            )
        );
    }
}
