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
/**
 * Auto generated from ClientMessageDtos.proto at 2018-08-13 09:37:00
 *
 * Prooph.EventStoreClient.Messages.ClientMessages package
 */

namespace Prooph\EventStoreClient\Messages\ClientMessages {
/**
 * ReadAllResult enum embedded in ReadAllEventsCompleted message
 */
final class ReadAllEventsCompleted_ReadAllResult
{
    const Success = 0;
    const NotModified = 1;
    const Error = 2;
    const AccessDenied = 3;

    /**
     * Returns defined enum values
     *
     * @return int[]
     */
    public function getEnumValues()
    {
        return [
            'Success' => self::Success,
            'NotModified' => self::NotModified,
            'Error' => self::Error,
            'AccessDenied' => self::AccessDenied,
        ];
    }
}
}
