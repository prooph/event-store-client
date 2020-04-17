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
use Amp\Delayed;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\TimeoutException;
use Generator;
use Prooph\EventStore\Async\ClientClosedEventArgs;
use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\EndPoint;
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
    public function should_not_throw_exception_when_server_is_down(): Generator
    {
        $connection = EventStoreConnectionFactory::createFromEndPoint(
            $this->blackhole
        );

        yield $connection->connectAsync();

        yield new Delayed(50); // wait for loop to finish
    }

    /** @test */
    public function should_throw_exception_when_trying_to_reopen_closed_connection(): Generator
    {
        $closed = new Deferred();
        $settings = ConnectionSettings::create()
            ->limitReconnectionsTo(0)
            ->withConnectionTimeoutOf(10000)
            ->setReconnectionDelayTo(0)
            ->failOnNoServerResponse()
            ->build();

        $connection = EventStoreConnectionFactory::createFromEndPoint(
            $this->blackhole,
            $settings
        );

        $connection->onClosed(function () use ($closed): void {
            $closed->resolve(true);
        });

        // Need a watcher to keep the loop running after connection is closed.
        $watcher = Loop::delay(180000, function (): void {
        });

        yield $connection->connectAsync();

        try {
            yield Promise\timeout($closed->promise(), 120000);
        } catch (TimeoutException $e) {
            $this->fail('Connection timeout took too long');
        }

        $this->expectException(InvalidOperationException::class);

        try {
            yield $connection->connectAsync();
        } finally {
            Loop::cancel($watcher);
        }
    }

    /** @test */
    public function should_close_connection_after_configured_amount_of_failed_reconnections(): Generator
    {
        $closed = new Deferred();
        $settings = ConnectionSettings::create()
            ->limitReconnectionsTo(1)
            ->withConnectionTimeoutOf(10000)
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

            $closed->resolve(true);
        });

        // Need a watcher to keep the loop running after connection is closed.
        $watcher = Loop::delay(180000, function (): void {
        });

        yield $connection->connectAsync();

        try {
            yield Promise\timeout($closed->promise(), 120000);
        } catch (TimeoutException $e) {
            $this->fail('Connection timeout took too long');
        }

        $this->expectException(InvalidOperationException::class);

        try {
            yield $connection->appendToStreamAsync('stream', ExpectedVersion::NO_STREAM, [TestEvent::newTestEvent()]);
        } finally {
            Loop::cancel($watcher);
        }
    }
}
