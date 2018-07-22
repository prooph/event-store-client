<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Amp\Failure;
use Amp\Promise;
use Amp\Success;
use Amp\Uri\InvalidUriException;
use Amp\Uri\Uri;
use Prooph\EventStoreClient\IpEndPoint;

/** @internal */
final class SingleEndpointDiscoverer implements EndPointDiscoverer
{
    /** @var string */
    private $connectionString;
    /** @var bool */
    private $useSslConnection;

    public function __construct(string $connectionString, bool $useSslConnection)
    {
        $this->connectionString = $connectionString;
        $this->useSslConnection = $useSslConnection;
    }

    public function discoverAsync(?IpEndPoint $failedTcpEndPoint): Promise
    {
        try {
            $uri = new Uri($this->connectionString);
        } catch (InvalidUriException $e) {
            return new Failure($e);
        }

        $endPoint = new IpEndPoint($uri->getHost(), $uri->getPort());

        return new Success(
            new NodeEndPoints(
                $this->useSslConnection ? null : $endPoint,
                $this->useSslConnection ? $endPoint : null
            )
        );
    }
}
