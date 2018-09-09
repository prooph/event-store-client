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

namespace ProophTest\EventStoreClient;

use Amp\Deferred;
use Amp\Delayed;
use Amp\Promise;
use Amp\TimeoutException;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStoreClient\EndPoint;
use Prooph\EventStoreClient\EventStoreAsyncConnectionBuilder;
use Prooph\EventStoreClient\Exception\InvalidOperationException;
use Prooph\EventStoreClient\ExpectedVersion;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;
use function Amp\call;
use function Amp\Promise\wait;

class connect extends TestCase
{
    /** @var EndPoint */
    private $blackhole;

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
            $connection = EventStoreAsyncConnectionBuilder::createFromSettingsWithIpEndPoint(
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
                ->failOnNoServerResponse();

            $connection = EventStoreAsyncConnectionBuilder::createFromSettingsWithIpEndPoint(
                $this->blackhole,
                $settings->build()
            );

            $connection->onClosed(function () use ($closed) {
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
                ->failOnNoServerResponse();

            $connection = EventStoreAsyncConnectionBuilder::createFromSettingsWithIpEndPoint(
                $this->blackhole,
                $settings->build()
            );

            $connection->onClosed(function () use ($closed) {
                $closed->resolve(true);
            });

            yield $connection->connectAsync();

            try {
                yield Promise\timeout($closed->promise(), 120000);
            } catch (TimeoutException $e) {
                $this->fail('Connection timeout took too long');
            }

            $this->expectException(InvalidOperationException::class);

            yield $connection->appendToStreamAsync('stream', ExpectedVersion::EMPTY_STREAM, [TestEvent::new()]);
        }));
    }
}
