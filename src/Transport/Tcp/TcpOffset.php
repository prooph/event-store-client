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

namespace Prooph\EventStoreClient\Transport\Tcp;

// @todo: remove this class and its references should use TcpPackage consts
class TcpOffset
{
    public const Int32Length = 4;
    public const CorrelationIdLength = 16;
    public const HeaderLength = 18;
    public const MessageTypeOffset = 4;
    public const FlagOffset = 5;
    public const CorrelationIdOffset = 6;
    public const DataOffset = 22;

    private function __construct()
    {
    }
}
