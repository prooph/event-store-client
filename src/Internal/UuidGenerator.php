<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Ramsey\Uuid\Uuid;

/** @internal */
class UuidGenerator
{
    public static function generate(): string
    {
        return \str_replace('-', '', Uuid::uuid4()->toString());
    }

    public static function empty(): string
    {
        return '00000000-0000-0000-0000-000000000000';
    }
}
