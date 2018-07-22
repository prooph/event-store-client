<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Exception;

class InvalidArgumentException extends \InvalidArgumentException implements EventStoreClientException
{
}
