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

use Amp\PHPUnit\AsyncTestCase;
use Prooph\EventStore\EventStoreSubscription;
use Prooph\EventStore\Exception\InvalidOperationException;
use Prooph\EventStore\Position;
use Prooph\EventStore\ResolvedEvent;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;

class event_store_connection_should extends AsyncTestCase
{
    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function not_throw_on_close_if_connect_was_not_called(): void
    {
        $connection = TestConnection::create();
        $connection->close();
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function not_throw_on_close_if_called_multiple_times(): void
    {
        $connection = TestConnection::create();
        $connection->close();
        $connection->close();
    }

    /** @test */
    public function throw_invalid_operation_on_every_api_call_if_connect_was_not_called(): void
    {
        $connection = TestConnection::create();

        $s = 'stream';
        $events = TestEvent::newAmount(1);

        try {
            $connection->deleteStream($s, 0);
            $this->fail('No exception thrown on DeleteStreamAsync');
        } catch (Throwable $e) {
            $this->assertInstanceOf(InvalidOperationException::class, $e);
        }

        try {
            $connection->appendToStream($s, 0, $events);
            $this->fail('No exception thrown on AppendToStreamAsync');
        } catch (Throwable $e) {
            $this->assertInstanceOf(InvalidOperationException::class, $e);
        }

        try {
            $connection->readStreamEventsForward($s, 0, 1);
            $this->fail('No exception thrown on ReadStreamEventsForwardAsync');
        } catch (Throwable $e) {
            $this->assertInstanceOf(InvalidOperationException::class, $e);
        }

        try {
            $connection->readStreamEventsBackward($s, 0, 1);
            $this->fail('No exception thrown on ReadStreamEventsBackwardAsync');
        } catch (Throwable $e) {
            $this->assertInstanceOf(InvalidOperationException::class, $e);
        }

        try {
            $connection->readAllEventsForward(Position::start(), 1);
            $this->fail('No exception thrown on ReadAllEventsForwardAsync');
        } catch (Throwable $e) {
            $this->assertInstanceOf(InvalidOperationException::class, $e);
        }

        try {
            $connection->readAllEventsBackward(Position::end(), 1);
            $this->fail('No exception thrown on ReadAllEventsBackwardAsync');
        } catch (Throwable $e) {
            $this->assertInstanceOf(InvalidOperationException::class, $e);
        }

        try {
            $connection->startTransaction($s, 0);
            $this->fail('No exception thrown on StartTransactionAsync');
        } catch (Throwable $e) {
            $this->assertInstanceOf(InvalidOperationException::class, $e);
        }

        try {
            $connection->subscribeToStream(
                $s,
                false,
                function (
                    EventStoreSubscription $subscription,
                    ResolvedEvent $resolvedEvent
                ): void {
                }
            );

            $this->fail('No exception thrown on SubscribeToStreamAsync');
        } catch (Throwable $e) {
            $this->assertInstanceOf(InvalidOperationException::class, $e);
        }

        try {
            $connection->subscribeToAll(
                false,
                function (
                    EventStoreSubscription $subscription,
                    ResolvedEvent $resolvedEvent
                ): void {
                }
            );

            $this->fail('No exception thrown on SubscribeToAllAsync');
        } catch (Throwable $e) {
            $this->assertInstanceOf(InvalidOperationException::class, $e);
        }

        $connection->close();
    }
}
