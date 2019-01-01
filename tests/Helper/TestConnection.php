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

namespace ProophTest\EventStoreClient\Helper;

use Prooph\EventStore\AsyncEventStoreConnection;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStoreClient\ConnectionSettingsBuilder;
use Prooph\EventStoreClient\EventStoreConnectionFactory;

/** @internal */
class TestConnection
{
    public static function create(?UserCredentials $userCredentials = null): AsyncEventStoreConnection
    {
        self::checkRequiredEnvironmentSettings();

        return EventStoreConnectionFactory::createFromEndPoint(
            self::endPoint(),
            self::settings($userCredentials)
        );
    }

    private static function checkRequiredEnvironmentSettings(): void
    {
        $env = \getenv();

        if (! isset(
            $env['ES_HOST'],
            $env['ES_PORT'],
            $env['ES_USER'],
            $env['ES_PASS']
        )) {
            throw new \RuntimeException('Environment settings for event store connection not found');
        }
    }

    private static function settings(?UserCredentials $userCredentials = null): ConnectionSettings
    {
        $settingsBuilder = new ConnectionSettingsBuilder();

        if ($userCredentials) {
            $settingsBuilder->setDefaultUserCredentials($userCredentials);
        }

        return $settingsBuilder->build();
    }

    private static function endPoint(): EndPoint
    {
        $host = (string) \getenv('ES_HOST');
        $port = (int) \getenv('ES_PORT');

        return new EndPoint($host, $port);
    }

    public static function httpEndPoint(): EndPoint
    {
        $env = \getenv();

        if (! isset(
            $env['ES_HOST'],
            $env['ES_HTTP_PORT']
        )) {
            throw new \RuntimeException('Environment settings for event store http endpoint not found');
        }

        $host = (string) \getenv('ES_HOST');
        $port = (int) \getenv('ES_HTTP_PORT');

        return new EndPoint($host, $port);
    }
}
