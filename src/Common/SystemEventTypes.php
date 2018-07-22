<?php
/**
 * This file is part of the prooph/event-store-client.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
