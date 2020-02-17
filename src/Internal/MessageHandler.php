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

use Closure;
use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStoreClient\Internal\Message\Message;

/** @internal */
class MessageHandler
{
    private array $handlers = [];

    public function registerHandler(string $messageName, Closure $handler): void
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
