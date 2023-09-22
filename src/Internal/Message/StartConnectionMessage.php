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

namespace Prooph\EventStoreClient\Internal\Message;

use Amp\DeferredFuture;
use Prooph\EventStoreClient\Internal\EndPointDiscoverer;

/**
 * @internal
 *
 * @psalm-immutable
 */
class StartConnectionMessage implements Message
{
    public function __construct(
        private readonly DeferredFuture $deferred,
        private readonly EndPointDiscoverer $endPointDiscoverer
    ) {
    }

    public function deferred(): DeferredFuture
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
