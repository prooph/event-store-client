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

namespace Prooph\EventStoreClient\SystemData;

/** @internal */
final class InspectionDecision
{
    public const OPTIONS = [
        'DoNothing' => 0,
        'EndOperation' => 1,
        'Retry' => 2,
        'Reconnect' => 3,
        'Subscribed' => 4,
    ];

    public const DO_NOTHING = 0;
    public const END_OPERATION = 1;
    public const RETRY = 2;
    public const RECONNECT = 3;
    public const SUBSCRIBED = 4;

    private $name;
    private $value;

    private function __construct(string $name)
    {
        $this->name = $name;
        $this->value = self::OPTIONS[$name];
    }

    public static function doNothing(): self
    {
        return new self('DoNothing');
    }

    public static function endOperation(): self
    {
        return new self('EndOperation');
    }

    public static function retry(): self
    {
        return new self('Retry');
    }

    public static function reconnect(): self
    {
        return new self('Reconnect');
    }

    public static function subscribed(): self
    {
        return new self('Subscribed');
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

    public function equals(InspectionDecision $other): bool
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
