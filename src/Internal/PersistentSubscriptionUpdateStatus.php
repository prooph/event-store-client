<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

/** @internal */
class PersistentSubscriptionUpdateStatus
{
    public const OPTIONS = [
        'Success' => 0,
        'NotFound' => 1,
        'Failure' => 2,
    ];

    public const Success = 0;
    public const NotFound = 1;
    public const Failure = 2;

    private $name;
    private $value;

    private function __construct(string $name)
    {
        $this->name = $name;
        $this->value = self::OPTIONS[$name];
    }

    public static function success(): self
    {
        return new self('Success');
    }

    public static function notFound(): self
    {
        return new self('NotFound');
    }

    public static function failure(): self
    {
        return new self('Failure');
    }

    public static function byName(string $value): self
    {
        if (! isset(self::OPTIONS[$value])) {
            throw new \InvalidArgumentException('Unknown enum name given');
        }

        return self::{$value}();
    }

    public static function byValue($value): self
    {
        foreach (self::OPTIONS as $name => $v) {
            if ($v === $value) {
                return self::{$name}();
            }
        }

        throw new \InvalidArgumentException('Unknown enum value given');
    }

    public function equals(PersistentSubscriptionUpdateStatus $other): bool
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
