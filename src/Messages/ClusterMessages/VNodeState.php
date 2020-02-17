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

namespace Prooph\EventStoreClient\Messages\ClusterMessages;

final class VNodeState
{
    public const OPTIONS = [
        'Initializing' => 0,
        'Unknown' => 1,
        'PreReplica' => 2,
        'CatchingUp' => 3,
        'Clone' => 4,
        'Slave' => 5,
        'PreMaster' => 6,
        'Master' => 7,
        'Manager' => 8,
        'ShuttingDown' => 9,
        'Shutdown' => 10,
    ];

    public const INITIALIZING = 0;
    public const UNKNOWN = 1;
    public const PRE_REPLICA = 2;
    public const CATCHING_UP = 3;
    public const CLONE = 4;
    public const SLAVE = 5;
    public const PRE_MASTER = 6;
    public const MASTER = 7;
    public const MANAGER = 8;
    public const SHUTTING_DOWN = 9;
    public const SHUTDOWN = 10;

    private string $name;
    private int $value;

    private function __construct(string $name)
    {
        $this->name = $name;
        $this->value = self::OPTIONS[$name];
    }

    public static function initializing(): self
    {
        return new self('Initializing');
    }

    public static function unknown(): self
    {
        return new self('Unknown');
    }

    public static function preReplica(): self
    {
        return new self('PreReplica');
    }

    public static function catchingUp(): self
    {
        return new self('CatchingUp');
    }

    public static function clone(): self
    {
        return new self('Clone');
    }

    public static function slave(): self
    {
        return new self('Slave');
    }

    public static function preMaster(): self
    {
        return new self('PreMaster');
    }

    public static function master(): self
    {
        return new self('Master');
    }

    public static function manager(): self
    {
        return new self('Manager');
    }

    public static function shuttingDown(): self
    {
        return new self('ShuttingDown');
    }

    public static function shutdown(): self
    {
        return new self('Shutdown');
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

    public function equals(VNodeState $other): bool
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
