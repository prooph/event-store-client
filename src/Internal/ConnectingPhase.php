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
final class ConnectingPhase
{
    public const OPTIONS = [
        'Invalid' => 0,
        'Reconnecting' => 1,
        'EndPointDiscovery' => 2,
        'ConnectionEstablishing' => 3,
        'Authentication' => 4,
        'Identification' => 5,
        'Connected' => 6,
    ];

    public const INVALID = 0;
    public const RECONNECTION = 1;
    public const END_POINT_DISCOVERY = 2;
    public const CONNECTION_ESTABLISHING = 3;
    public const AUTHENTICATION = 4;
    public const IDENTIFICATION = 5;
    public const CONNECTED = 6;

    private string $name;
    private int $value;

    private function __construct(string $name)
    {
        $this->name = $name;
        $this->value = self::OPTIONS[$name];
    }

    public static function invalid(): self
    {
        return new self('Invalid');
    }

    public static function reconnecting(): self
    {
        return new self('Reconnecting');
    }

    public static function endPointDiscovery(): self
    {
        return new self('EndPointDiscovery');
    }

    public static function connectionEstablishing(): self
    {
        return new self('ConnectionEstablishing');
    }

    public static function authentication(): self
    {
        return new self('Authentication');
    }

    public static function identification(): self
    {
        return new self('Identification');
    }

    public static function connected(): self
    {
        return new self('Connected');
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

    public function equals(ConnectingPhase $other): bool
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
