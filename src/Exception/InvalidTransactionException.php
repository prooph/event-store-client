<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Exception;

/**
 * Exception thrown if there is an attempt to operate inside a
 * transaction which does not exist.
 */
class InvalidTransactionException extends RuntimeException
{
}
