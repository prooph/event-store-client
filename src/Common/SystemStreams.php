<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Common;

class SystemStreams
{
    public const PersistentSubscriptionConfig = '$persistentSubscriptionConfig';
    public const AllStream = '$all';
    public const StreamsStream = '$streams';
    public const SettingsStream = '$settings';
    public const StatsStreamPrefix = '$stats';
    public const ScavengesStream = '$scavenges';

    public static function metastreamOf(string $streamId): string
    {
        return '$$' . $streamId;
    }

    public static function isMetastream(string $streamId): bool
    {
        return \strlen($streamId) > 1 && \substr($streamId, 0, 2) === '$$';
    }

    public static function originalStreamOf(string $metastreamId): string
    {
        return \substr($metastreamId, 2);
    }

    public static function isSystemStream(string $streamId): bool
    {
        return \strlen($streamId) !== 0 && $streamId[0] === '$';
    }
}
