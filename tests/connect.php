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
use function Amp\delay;
use Amp\PHPUnit\AsyncTestCase;
use Amp\TimeoutCancellation;
use Prooph\EventStore\ClientClosedEventArgs;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\EventStoreConnection;
use Prooph\EventStore\Exception\InvalidOperationException;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStoreClient\EventStoreConnectionFactory;
use ProophTest\EventStoreClient\Helper\TestEvent;

class connect extends AsyncTestCase
{
    private EndPoint $blackhole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->blackhole = new EndPoint('localhost', 9999);
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function should_not_throw_exception_when_server_is_down(): void
    {
        $connection = EventStoreConnectionFactory::createFromEndPoint(
            $this->blackhole
        );

        $connection->connect();

        delay(0.05); // wait for loop to finish
    }

    /** @test */
    public function should_throw_exception_when_trying_to_reopen_closed_connection(): void
    {
        $closed = new DeferredFuture();
        $settings = ConnectionSettings::create()
            ->limitReconnectionsTo(0)
            ->withConnectionTimeoutOf(10)
            ->setReconnectionDelayTo(0)
            ->failOnNoServerResponse()
            ->build();

        $connection = EventStoreConnectionFactory::createFromEndPoint(
            $this->blackhole,
            $settings
        );

        $connection->onClosed(function () use ($closed): void {
            $closed->complete(true);
        });

        $connection->connect();

        try {
            $closed->getFuture()->await(new TimeoutCancellation(120));
        } catch (CancelledException $e) {
            $this->fail('Connection timeout took too long');
        }

        $this->expectException(InvalidOperationException::class);

        $connection->connect();
    }

    /** @test */
    public function should_close_connection_after_configured_amount_of_failed_reconnections(): void
    {
        $closed = new DeferredFuture();
        $settings = ConnectionSettings::create()
            ->limitReconnectionsTo(1)
            ->withConnectionTimeoutOf(10)
            ->setReconnectionDelayTo(0)
            ->failOnNoServerResponse()
            ->build();

        $connection = EventStoreConnectionFactory::createFromEndPoint(
            $this->blackhole,
            $settings
        );

        $connection->onClosed(function (ClientClosedEventArgs $args) use ($closed): void {
            $this->assertInstanceOf(EventStoreConnection::class, $args->connection());
            $this->assertSame('Reconnection limit reached', $args->reason());

            $closed->complete(true);
        });

        $connection->connect();

        try {
            $closed->getFuture()->await(new TimeoutCancellation(50));
        } catch (CancelledException $e) {
            $this->fail('Connection timeout took too long');
        }

        $this->expectException(InvalidOperationException::class);

        $connection->appendToStream('stream', ExpectedVersion::NoStream, [TestEvent::newTestEvent()]);
    }
}
