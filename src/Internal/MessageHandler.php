<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2024 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2024 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
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
    /** @var array<string, Closure> */
    private array $handlers = [];

    public function registerHandler(string $messageName, Closure $handler): void
    {
        $this->handlers[$messageName] = $handler;
    }

    public function handle(Message $message): void
    {
        if (! isset($this->handlers[$message::class])) {
            throw new RuntimeException('No handler found for ' . $message::class);
        }

        $this->handlers[$message::class]($message);
    }
}
