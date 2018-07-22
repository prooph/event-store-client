<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

class ExpectedVersion
{
    // This write should not conflict with anything and should always succeed.
    public const Any = -2;
    // The stream being written to should not yet exist. If it does exist treat that as a concurrency problem.
    public const NoStream = -1;
    // The stream should exist and should be empty. If it does not exist or is not empty treat that as a concurrency problem.
    public const EmptyStream = 0;
    // The stream is invalid
    public const Invalid = -3;
    // The stream should exist. If it or a metadata stream does not exist treat that as a concurrency problem.
    public const StreamExists = -4;
}
