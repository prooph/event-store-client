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

namespace Prooph\EventStoreClient\Util;

class UuidGenerator
{
    public static function generate(): string
    {
        $uuidBin = \random_bytes(18);
        $uuidBin &= "\xFF\xFF\xFF\xFF\x0F\xFF\xF0\x0F\xFF\x03\xFF\xF0\xFF\xFF\xFF\xFF\xFF\xFF";
        $uuidBin |= "\x00\x00\x00\x00\x00\x00\x00\x40\x00\x08\x00\x00\x00\x00\x00\x00\x00\x00";
        $uuidHex = \bin2hex($uuidBin);
        $uuidHex[8] = $uuidHex[13] = $uuidHex[18] = $uuidHex[23] = '-';

        return $uuidHex;
    }

    public static function generateWithoutDash(): string
    {
        $uuidBin = \random_bytes(16);
        $uuidBin &= "\xFF\xFF\xFF\xFF\xFF\xFF\x0F\xFF\x3F\xFF\xFF\xFF\xFF\xFF\xFF\xFF";
        $uuidBin |= "\x00\x00\x00\x00\x00\x00\x40\x00\x80\x00\x00\x00\x00\x00\x00\x00";

        return \bin2hex($uuidBin);
    }

    public static function empty(): string
    {
        return '00000000-0000-0000-0000-000000000000';
    }

    final private function __construct()
    {
    }
}
