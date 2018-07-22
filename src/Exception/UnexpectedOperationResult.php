<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Exception;

class UnexpectedOperationResult extends RuntimeException
{
    public function __construct(string $message = 'Unexpected operation result')
    {
        parent::__construct($message);
    }
}
