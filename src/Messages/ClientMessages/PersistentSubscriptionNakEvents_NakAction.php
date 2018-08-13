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
 * NakAction enum embedded in PersistentSubscriptionNakEvents message
 */
final class PersistentSubscriptionNakEvents_NakAction
{
    const Unknown = 0;
    const Park = 1;
    const Retry = 2;
    const Skip = 3;
    const Stop = 4;

    /**
     * Returns defined enum values
     *
     * @return int[]
     */
    public function getEnumValues()
    {
        return [
            'Unknown' => self::Unknown,
            'Park' => self::Park,
            'Retry' => self::Retry,
            'Skip' => self::Skip,
            'Stop' => self::Stop,
        ];
    }
}
}
