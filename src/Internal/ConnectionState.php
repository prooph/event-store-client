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

/** @internal */
final class ConnectionState
{
    public const OPTIONS = [
        'Init' => 0,
        'Connecting' => 1,
        'Connected' => 2,
        'Closed' => 3,
    ];

    public const INIT = 0;
    public const CONNECTING = 1;
    public const CONNECTED = 2;
    public const CLOSED = 3;

    private string $name;
    private int $value;

    private function __construct(string $name)
    {
        $this->name = $name;
        $this->value = self::OPTIONS[$name];
    }

    public static function init(): self
    {
        return new self('Init');
    }

    public static function connecting(): self
    {
        return new self('Connecting');
    }

    public static function connected(): self
    {
        return new self('Connected');
    }

    public static function closed(): self
    {
        return new self('Closed');
    }

    public static function fromName(string $value): self
    {
        if (! isset(self::OPTIONS[$value])) {
            throw new \InvalidArgumentException('Unknown enum name given');
        }

        return self::{$value}();
    }

    public static function fromValue(int $value): self
    {
        foreach (self::OPTIONS as $name => $v) {
            if ($v === $value) {
                return self::{$name}();
            }
        }

        throw new \InvalidArgumentException('Unknown enum value given');
    }

    public function equals(ConnectionState $other): bool
    {
        return $this->name === $other->name;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function value(): int
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
