<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2019 Alexander Miertsch <kontakt@codeliner.ws>
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
 * NotHandledReason enum embedded in NotHandled message
 */
final class NotHandled_NotHandledReason
{
    const NotReady = 0;
    const TooBusy = 1;
    const NotMaster = 2;

    /**
     * Returns defined enum values
     *
     * @return int[]
     */
    public function getEnumValues()
    {
        return [
            'NotReady' => self::NotReady,
            'TooBusy' => self::TooBusy,
            'NotMaster' => self::NotMaster,
        ];
    }
}
}
