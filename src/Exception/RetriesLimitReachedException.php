<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Exception;

class RetriesLimitReachedException extends RuntimeException
{
    public static function with(int $retries): RetriesLimitReachedException
    {
        return new self(
            \sprintf(
                'Operation reached retries limit: \'%s\'',
                $retries
            )
        );
    }
}
