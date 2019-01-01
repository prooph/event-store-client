<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2019 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);
/**
 * Auto generated from ClientMessageDtos.proto at 2018-08-13 09:37:00
 *
 * Prooph.EventStoreClient.Messages.ClientMessages package
 */

namespace Prooph\EventStoreClient\Messages\ClientMessages {
/**
 * ReadStreamResult enum embedded in ReadStreamEventsCompleted message
 */
final class ReadStreamEventsCompleted_ReadStreamResult
{
    const Success = 0;
    const NoStream = 1;
    const StreamDeleted = 2;
    const NotModified = 3;
    const Error = 4;
    const AccessDenied = 5;

    /**
     * Returns defined enum values
     *
     * @return int[]
     */
    public function getEnumValues()
    {
        return [
            'Success' => self::Success,
            'NoStream' => self::NoStream,
            'StreamDeleted' => self::StreamDeleted,
            'NotModified' => self::NotModified,
            'Error' => self::Error,
            'AccessDenied' => self::AccessDenied,
        ];
    }
}
}
