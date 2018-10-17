<?php

/**
 * This file is part of `prooph/event-store-client`.
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
 * SubscriptionDropReason enum embedded in SubscriptionDropped message
 */
final class SubscriptionDropped_SubscriptionDropReason
{
    const Unsubscribed = 0;
    const AccessDenied = 1;
    const NotFound = 2;
    const PersistentSubscriptionDeleted = 3;
    const SubscriberMaxCountReached = 4;

    /**
     * Returns defined enum values
     *
     * @return int[]
     */
    public function getEnumValues()
    {
        return [
            'Unsubscribed' => self::Unsubscribed,
            'AccessDenied' => self::AccessDenied,
            'NotFound' => self::NotFound,
            'PersistentSubscriptionDeleted' => self::PersistentSubscriptionDeleted,
            'SubscriberMaxCountReached' => self::SubscriberMaxCountReached,
        ];
    }
}
}
