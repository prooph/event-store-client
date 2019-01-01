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
 * OperationResult enum
 */
final class OperationResult
{
    const Success = 0;
    const PrepareTimeout = 1;
    const CommitTimeout = 2;
    const ForwardTimeout = 3;
    const WrongExpectedVersion = 4;
    const StreamDeleted = 5;
    const InvalidTransaction = 6;
    const AccessDenied = 7;

    /**
     * Returns defined enum values
     *
     * @return int[]
     */
    public function getEnumValues()
    {
        return [
            'Success' => self::Success,
            'PrepareTimeout' => self::PrepareTimeout,
            'CommitTimeout' => self::CommitTimeout,
            'ForwardTimeout' => self::ForwardTimeout,
            'WrongExpectedVersion' => self::WrongExpectedVersion,
            'StreamDeleted' => self::StreamDeleted,
            'InvalidTransaction' => self::InvalidTransaction,
            'AccessDenied' => self::AccessDenied,
        ];
    }
}
}
