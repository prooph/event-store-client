<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Prooph\EventStoreClient\EventRecord;
use Prooph\EventStoreClient\Position;

interface ResolvedEvent
{
    public function originalEvent(): ?EventRecord;

    public function originalPosition(): ?Position;

    public function originalStreamName(): string;

    public function originalEventNumber(): int;
}
