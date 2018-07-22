<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

class ListenerHandler
{
    /** @var callable */
    private $listener;

    /** @internal */
    public function __construct(callable $listener)
    {
        $this->listener = $listener;
    }

    public function callback(): callable
    {
        return $this->listener;
    }
}
