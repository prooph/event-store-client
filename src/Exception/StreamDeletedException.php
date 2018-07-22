<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Exception;

class StreamDeletedException extends RuntimeException
{
    public static function with(string $stream): StreamDeletedException
    {
        return new self(\sprintf(
            'Stream \'%s\' is deleted',
            $stream
        ));
    }
}
