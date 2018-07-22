<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Exception;

class MaxQueueSizeLimitReachedException extends RuntimeException
{
    public static function with(string $connectionName, int $maxQueueSize): MaxQueueSizeLimitReachedException
    {
        return new self(
            \sprintf(
                'EventStoreNodeConnection \'%s\': reached max queue size limit: \'%s\'',
                $connectionName,
                $maxQueueSize
            )
        );
    }
}
