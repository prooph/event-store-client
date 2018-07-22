<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\Message;

/** @internal */
interface Message
{
    public function __toString(): string;
}
