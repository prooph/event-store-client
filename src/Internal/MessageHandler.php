<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Prooph\EventStoreClient\Exception\RuntimeException;
use Prooph\EventStoreClient\Internal\Message\Message;

/** @internal */
class MessageHandler
{
    /** @var array */
    private $handlers = [];

    public function registerHandler(string $messageName, callable $handler): void
    {
        $this->handlers[$messageName] = $handler;
    }

    public function handle(Message $message): void
    {
        $name = \get_class($message);

        if (! isset($this->handlers[$name])) {
            throw new RuntimeException('No handler found for ' . $name);
        }

        $this->handlers[$name]($message);
    }
}
