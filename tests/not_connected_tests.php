<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2022 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2022 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\CancelledException;
use Amp\DeferredFuture;
use Amp\PHPUnit\AsyncTestCase;
use Amp\TimeoutCancellation;
use Prooph\EventStore\EndPoint;
use Prooph\EventStoreClient\ConnectionSettingsBuilder;
use Prooph\EventStoreClient\EventStoreConnectionFactory;

class not_connected_tests extends AsyncTestCase
{
    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function should_timeout_connection_after_configured_amount_time_on_connect(): void
    {
        $settingsBuilder = (new ConnectionSettingsBuilder())
            ->limitReconnectionsTo(0)
            ->setReconnectionDelayTo(0)
            ->failOnNoServerResponse()
            ->withConnectionTimeoutOf(1);

        $ip = '8.8.8.8'; //NOTE: This relies on Google DNS server being configured to swallow nonsense traffic
        $port = 4567;

        $connection = EventStoreConnectionFactory::createFromEndPoint(
            new EndPoint($ip, $port),
            $settingsBuilder->build(),
            'test-connection'
        );

        $deferred = new DeferredFuture();

        $connection->onConnected(function (): void {
            \var_dump('connected');
        });

        $connection->onReconnecting(function (): void {
            \var_dump('reconnecting');
        });

        $connection->onDisconnected(function (): void {
            \var_dump('disconnected');
        });

        $connection->onErrorOccurred(function (): void {
            \var_dump('error');
        });

        $connection->onClosed(function () use ($deferred): void {
            $deferred->complete();
        });

        $connection->connect();

        try {
            $deferred->getFuture()->await(new TimeoutCancellation(5));
        } catch (CancelledException $e) {
            $this->fail('Connection timeout took too long');
        }

        $connection->close();
    }
}
