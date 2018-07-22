<?php
/**
 * This file is part of the prooph/event-store-client.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
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

    public const Invalid = 0;
    public const Reconnecting = 1;
    public const EndPointDiscovery = 2;
    public const ConnectionEstablishing = 3;
    public const Authentication = 4;
    public const Identification = 5;
    public const Connected = 6;

    private $name;
    private $value;

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

    public static function fromValue($value): self
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
        return \get_class($this) === \get_class($other) && $this->name === $other->name;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function value()
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
