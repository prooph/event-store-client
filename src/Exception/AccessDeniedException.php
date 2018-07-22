<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Exception;

class AccessDeniedException extends RuntimeException
{
    public static function toAllStream(): AccessDeniedException
    {
        return new self(\sprintf(
            'Access to stream \'%s\' is denied',
            '$all'
        ));
    }

    public static function toStream(string $stream): AccessDeniedException
    {
        return new self(\sprintf(
            'Access to stream \'%s\' is denied',
            $stream
        ));
    }
}
