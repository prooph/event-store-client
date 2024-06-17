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

namespace Prooph\EventStoreClient\Internal\Message;

use Prooph\EventStoreClient\ClientOperations\ClientOperation;

/**
 * @internal
 *
 * @psalm-immutable
 */
class StartOperationMessage implements Message
{
    public function __construct(
        private readonly ClientOperation $operation,
        private readonly int $maxRetries,
        private readonly float $timeout
    ) {
    }

    public function operation(): ClientOperation
    {
        return $this->operation;
    }

    public function maxRetries(): int
    {
        return $this->maxRetries;
    }

    public function timeout(): float
    {
        return $this->timeout;
    }

    public function __toString(): string
    {
        return 'StartOperationMessage';
    }
}
