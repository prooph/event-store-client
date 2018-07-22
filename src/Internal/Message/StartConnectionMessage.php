<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\Message;

use Amp\Deferred;
use Prooph\EventStoreClient\Internal\EndPointDiscoverer;

/** @internal */
class StartConnectionMessage implements Message
{
    /** @var Deferred */
    private $deferred;
    /** @var EndPointDiscoverer */
    private $endPointDiscoverer;

    public function __construct(Deferred $deferred, EndPointDiscoverer $endPointDiscoverer)
    {
        $this->deferred = $deferred;
        $this->endPointDiscoverer = $endPointDiscoverer;
    }

    public function deferred(): Deferred
    {
        return $this->deferred;
    }

    public function endPointDiscoverer(): EndPointDiscoverer
    {
        return $this->endPointDiscoverer;
    }

    public function __toString(): string
    {
        return 'StartConnectionMessage';
    }
}
