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

namespace Prooph\EventStoreClient\Internal\Message;

use Amp\Deferred;
use Prooph\EventStoreClient\Internal\EndPointDiscoverer;

/** @internal */
class StartConnectionMessage implements Message
{
    private Deferred $deferred;
    private EndPointDiscoverer $endPointDiscoverer;

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
