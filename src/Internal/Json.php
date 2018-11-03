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

namespace Prooph\EventStoreClient\Internal;

use Amp\Deferred;
use Prooph\EventStoreClient\Exception\JsonException;

class Json
{
    /**
     * @param mixed $value
     * @return string
     */
    public static function encode($value): string
    {
        $flags = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION;

        $string = \json_encode($value, $flags);

        if ($error = \json_last_error()) {
            throw new JsonException(\json_last_error_msg(), $error);
        }

        return $string;
    }

    /**
     * @param string $json
     * @return mixed
     */
    public static function decode(string $json)
    {
        $data = \json_decode($json, true, 512, \JSON_BIGINT_AS_STRING);

        if ($error = \json_last_error()) {
            throw new JsonException(\json_last_error_msg(), $error);
        }

        return $data;
    }
}
