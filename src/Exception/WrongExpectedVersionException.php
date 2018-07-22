<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Exception;

class WrongExpectedVersionException extends RuntimeException
{
    public static function withExpectedVersion(string $stream, int $expectedVersion): WrongExpectedVersionException
    {
        return new self(\sprintf(
            'Append failed due to WrongExpectedVersion. Stream: %s, Expected version: %d',
            $stream,
            $expectedVersion
        ));
    }
}
