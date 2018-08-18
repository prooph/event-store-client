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

namespace Prooph\EventStoreClient\Transport\Http;

use Prooph\EventStoreClient\IpEndPoint;

/** @internal */
class EndpointExtensions
{
    public const HttpSchema = 'http';
    public const HttpsSchema = 'https';

    public static function rawUrlToHttpUrl(
        IpEndPoint $endPoint,
        string $schema,
        string $rawUrl = ''
    ): string {
        return self::createHttpUrl(
            $schema,
            $endPoint->host(),
            $endPoint->port(),
            \ltrim($rawUrl, '/')
        );
    }

    public static function formatStringToHttpUrl(
        IpEndPoint $endPoint,
        string $schema,
        string $formatString,
        ...$args
    ): string {
        return self::createHttpUrl(
            $schema,
            $endPoint->host(),
            $endPoint->port(),
            \sprintf(\ltrim($formatString, '/'), ...$args)
        );
    }

    private static function createHttpUrl(string $schema, string $host, int $port, string $path): string
    {
        return \sprintf(
            '%s://%s:%d/%s',
            $schema,
            $host,
            $port,
            $path
        );
    }
}
