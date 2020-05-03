<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2020 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2020 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\Deferred;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\Promise\timeout;
use Amp\TimeoutException;
use Prooph\EventStore\EndPoint;
use Prooph\EventStoreClient\ConnectionSettingsBuilder;
use Prooph\EventStoreClient\EventStoreConnectionFactory;

class not_connected_tests extends AsyncTestCase
{
    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function should_timeout_connection_after_configured_amount_time_on_connect(): \Generator
    {
        $settingsBuilder = (new ConnectionSettingsBuilder())
            ->limitReconnectionsTo(0)
            ->setReconnectionDelayTo(0)
            ->failOnNoServerResponse()
            ->withConnectionTimeoutOf(1000);

        $ip = '8.8.8.8'; //NOTE: This relies on Google DNS server being configured to swallow nonsense traffic
        $port = 4567;

        $connection = EventStoreConnectionFactory::createFromEndPoint(
            new EndPoint($ip, $port),
            $settingsBuilder->build(),
            'test-connection'
        );

        $deferred = new Deferred();

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
            $deferred->resolve();
        });

        // Need a watcher to keep the loop running after connection is closed.
        $watcher = Loop::delay(180000, function (): void {
        });

        yield $connection->connectAsync();

        try {
            yield timeout($deferred->promise(), 5000);
        } catch (TimeoutException $e) {
            Loop::cancel($watcher);
            $this->fail('Connection timeout took too long');
        }

        $connection->close();

        Loop::cancel($watcher);
    }
}
