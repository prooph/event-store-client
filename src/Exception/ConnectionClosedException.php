<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Exception;

class ConnectionClosedException extends EventStoreConnectionException
{
    public static function withName(string $name): ConnectionClosedException
    {
        return new self(\sprintf(
            'Connection \'%s\' was closed',
            $name
        ));
    }
}
