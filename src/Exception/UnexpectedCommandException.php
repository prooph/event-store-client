<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Exception;

class UnexpectedCommandException extends RuntimeException
{
    public static function withName(string $actualCommand): UnexpectedCommandException
    {
        return new self(\sprintf(
            'Unexpected command \'%s\'',
            $actualCommand
        ));
    }

    public static function with(string $expectedCommand, string $actualCommand): UnexpectedCommandException
    {
        return new self(\sprintf(
            'Unexpected command \'%s\': expected \'%s\'',
            $actualCommand,
            $expectedCommand
        ));
    }
}
