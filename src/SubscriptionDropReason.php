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

namespace Prooph\EventStoreClient;

class SubscriptionDropReason
{
    public const OPTIONS = [
        'UserInitiated' => 0,
        'NotAuthenticated' => 1,
        'AccessDenied' => 2,
        'SubscribingError' => 3,
        'ServerError' => 4,
        'ConnectionClosed' => 5,
        'CatchUpError' => 6,
        'ProcessingQueueOverflow' => 7,
        'EventHandlerException' => 8,
        'MaxSubscribersReached' => 9,
        'PersistentSubscriptionDeleted' => 10,
        'Unknown' => 100,
        'NotFound' => 11,
    ];

    public const UserInitiated = 0;
    public const NotAuthenticated = 1;
    public const AccessDenied = 2;
    public const SubscribingError = 3;
    public const ServerError = 4;
    public const ConnectionClosed = 5;
    public const CatchUpError = 6;
    public const ProcessingQueueOverflow = 7;
    public const EventHandlerException = 8;
    public const MaxSubscribersReached = 9;
    public const PersistentSubscriptionDeleted = 10;
    public const Unknown = 100;
    public const NotFound = 11;

    private $name;
    private $value;

    private function __construct(string $name)
    {
        $this->name = $name;
        $this->value = self::OPTIONS[$name];
    }

    public static function userInitiated(): self
    {
        return new self('UserInitiated');
    }

    public static function notAuthenticated(): self
    {
        return new self('NotAuthenticated');
    }

    public static function accessDenied(): self
    {
        return new self('AccessDenied');
    }

    public static function subscribingError(): self
    {
        return new self('SubscribingError');
    }

    public static function serverError(): self
    {
        return new self('ServerError');
    }

    public static function connectionClosed(): self
    {
        return new self('ConnectionClosed');
    }

    public static function catchUpError(): self
    {
        return new self('CatchUpError');
    }

    public static function processingQueueOverflow(): self
    {
        return new self('ProcessingQueueOverflow');
    }

    public static function eventHandlerException(): self
    {
        return new self('EventHandlerException');
    }

    public static function maxSubscribersReached(): self
    {
        return new self('MaxSubscribersReached');
    }

    public static function persistentSubscriptionDeleted(): self
    {
        return new self('PersistentSubscriptionDeleted');
    }

    public static function unknown(): self
    {
        return new self('Unknown');
    }

    public static function notFound(): self
    {
        return new self('NotFound');
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

    public function equals(SubscriptionDropReason $other): bool
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
