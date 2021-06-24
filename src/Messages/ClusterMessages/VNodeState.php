<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2021 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2021 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\Messages\ClusterMessages;

/** @psalm-immutable */
final class VNodeState
{
    public const OPTIONS = [
        'Initializing' => 1,
        'ReadOnlyLeaderless' => 2,
        'Unknown' => 3,
        'PreReadOnlyReplica' => 4,
        'PreReplica' => 5,
        'CatchingUp' => 6,
        'Clone' => 7,
        'ReadOnlyReplica' => 8,
        'Slave' => 9,
        'Follower' => 10,
        'PreMaster' => 11,
        'PreLeader' => 12,
        'Master' => 13,
        'Leader' => 14,
        'Manager' => 15,
        'ShuttingDown' => 16,
        'Shutdown' => 17,
    ];

    public const Initializing = 1;
    public const ReadOnlyLeaderless = 2;
    public const Unknown = 3;
    public const PreReadOnlyReplica = 4;
    public const PreReplica = 5;
    public const CatchingUp = 6;
    public const Clone = 7;
    public const ReadOnlyReplica = 8;
    public const Slave = 9;
    public const Follower = 10;
    public const PreMaster = 11;
    public const PreLeader = 12;
    public const Master = 13;
    public const Leader = 14;
    public const Manager = 15;
    public const ShuttingDown = 16;
    public const Shutdown = 17;

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

    public static function readOnlyLeaderless(): self
    {
        return new self('ReadOnlyLeaderless');
    }

    public static function unknown(): self
    {
        return new self('Unknown');
    }

    public static function preReadOnlyReplica(): self
    {
        return new self('PreReadOnlyReplica');
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

    public static function readOnlyReplica(): self
    {
        return new self('ReadOnlyReplica');
    }

    public static function slave(): self
    {
        return new self('Slave');
    }

    public static function follower(): self
    {
        return new self('Follower');
    }

    public static function preMaster(): self
    {
        return new self('PreMaster');
    }

    public static function preLeader(): self
    {
        return new self('PreLeader');
    }

    public static function master(): self
    {
        return new self('Master');
    }

    public static function leader(): self
    {
        return new self('Leader');
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

        return new self($value);
    }

    public static function fromValue(int $value): self
    {
        foreach (self::OPTIONS as $name => $v) {
            if ($v === $value) {
                return new self($name);
            }
        }

        throw new \InvalidArgumentException('Unknown enum value given');
    }

    /** @psalm-pure */
    public function equals(VNodeState $other): bool
    {
        return $this->name === $other->name;
    }

    /** @psalm-pure */
    public function name(): string
    {
        return $this->name;
    }

    /** @psalm-pure */
    public function value(): int
    {
        return $this->value;
    }

    /** @psalm-pure */
    public function __toString(): string
    {
        return $this->name;
    }
}
