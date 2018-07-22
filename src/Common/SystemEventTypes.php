<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Common;

class SystemEventTypes
{
    // event type for stream deleted
    public const StreamDeleted = '$streamDeleted';
    // event type for statistics
    public const StatsCollection = '$statsCollected';
    // event type for linkTo
    public const LinkTo = '$>';
    // event type for stream metadata
    public const StreamMetadata = '$metadata';
    // event type for the system settings
    public const Settings = '$settings';
}
