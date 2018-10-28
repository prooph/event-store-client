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

namespace ProophTest\EventStoreClient\Helper;

use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStoreClient\ConnectionSettingsBuilder;
use Prooph\EventStoreClient\EndPoint;
use Prooph\EventStoreClient\EventStoreAsyncConnection;
use Prooph\EventStoreClient\EventStoreAsyncConnectionBuilder;
use Prooph\EventStoreClient\EventStoreSyncConnection;
use Prooph\EventStoreClient\EventStoreSyncConnectionBuilder;
use Prooph\EventStoreClient\UserCredentials;

/** @internal */
class TestConnection
{
    public static function createAsync(?UserCredentials $userCredentials = null): EventStoreAsyncConnection
    {
        self::checkRequiredEnvironmentSettings();

        return EventStoreAsyncConnectionBuilder::createFromSettingsWithIpEndPoint(
            self::endPoint(),
            self::settings($userCredentials)
        );
    }

    public static function createSync(?UserCredentials $userCredentials = null): EventStoreSyncConnection
    {
        self::checkRequiredEnvironmentSettings();

        return EventStoreSyncConnectionBuilder::createFromSettingsWithEndPoint(
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
        $host = \getenv('ES_HOST');
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

        $host = \getenv('ES_HOST');
        $port = (int) \getenv('ES_HTTP_PORT');

        return new EndPoint($host, $port);
    }
}
