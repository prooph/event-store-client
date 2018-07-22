<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Exception;

class ServerError extends RuntimeException
{
    public function __construct(string $message = '')
    {
        if ('' !== $message) {
            $message = ': ' . $message;
        }

        parent::__construct('Server error' . $message);
    }
}
