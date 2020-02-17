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

use function Amp\call;
use Amp\Deferred;
use Amp\Delayed;
use Amp\Promise;
use function Amp\Promise\wait;
use Amp\TimeoutException;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Async\ClientClosedEventArgs;
use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Exception\InvalidOperationException;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStoreClient\EventStoreConnectionFactory;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;

class connect extends TestCase
{
    private EndPoint $blackhole;

    protected function setUp(): void
    {
        $this->blackhole = new EndPoint('localhost', 9999);
    }

    /**
     * @test
     * @doesNotPerformAssertions
     * @throws Throwable
     */
    public function should_not_throw_exception_when_server_is_down(): void
    {
        wait(call(function () {
            $connection = EventStoreConnectionFactory::createFromEndPoint(
                $this->blackhole
            );

            yield $connection->connectAsync();

            yield new Delayed(50); // wait for loop to finish
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_throw_exception_when_trying_to_reopen_closed_connection(): void
    {
        wait(call(function () {
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

            yield $connection->connectAsync();

            try {
                yield Promise\timeout($closed->promise(), 120000);
            } catch (TimeoutException $e) {
                $this->fail('Connection timeout took too long');
            }

            $this->expectException(InvalidOperationException::class);

            yield $connection->connectAsync();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_close_connection_after_configured_amount_of_failed_reconnections(): void
    {
        wait(call(function () {
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

            $connection->onClosed(function (ClientClosedEventArgs $args) use ($closed): void {
                $this->assertInstanceOf(EventStoreConnection::class, $args->connection());
                $this->assertEquals('Reconnection limit reached', $args->reason());

                $closed->resolve(true);
            });

            yield $connection->connectAsync();

            try {
                yield Promise\timeout($closed->promise(), 120000);
            } catch (TimeoutException $e) {
                $this->fail('Connection timeout took too long');
            }

            $this->expectException(InvalidOperationException::class);

            yield $connection->appendToStreamAsync('stream', ExpectedVersion::NO_STREAM, [TestEvent::newTestEvent()]);
        }));
    }
}
