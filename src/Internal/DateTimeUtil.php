<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use DateTimeImmutable;
use DateTimeZone;

abstract class DateTimeUtil
{
    public static function utcNow(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public static function create(string $dateTimeString): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat(
            'Y-m-d\TH:i:s.u\Z',
            $dateTimeString,
            new DateTimeZone('UTC')
        );
    }

    public static function format(DateTimeImmutable $dateTime): string
    {
        return $dateTime->format('Y-m-d\TH:i:s.u\Z');
    }
}
