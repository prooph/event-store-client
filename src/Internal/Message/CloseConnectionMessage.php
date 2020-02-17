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

use Throwable;

/** @internal */
class CloseConnectionMessage implements Message
{
    private string $reason;
    private ?Throwable $exception;

    public function __construct(string $reason, ?Throwable $exception = null)
    {
        $this->reason = $reason;
        $this->exception = $exception;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function exception(): ?Throwable
    {
        return $this->exception;
    }

    public function __toString(): string
    {
        return 'CloseConnectionMessage';
    }
}
