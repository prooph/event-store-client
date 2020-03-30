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

use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use Prooph\EventStore\Async\EventAppearedOnSubscription;
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

    /**
     * @test
     */
    public function throw_invalid_operation_on_every_api_call_if_connect_was_not_called(): \Generator
    {
        $connection = TestConnection::create();

        $s = 'stream';
        $events = TestEvent::newAmount(1);

        try {
            yield $connection->deleteStreamAsync($s, 0);
            $this->fail('No exception thrown on DeleteStreamAsync');
        } catch (Throwable $e) {
            $this->assertInstanceOf(InvalidOperationException::class, $e);
        }

        try {
            yield $connection->appendToStreamAsync($s, 0, $events);
            $this->fail('No exception thrown on AppendToStreamAsync');
        } catch (Throwable $e) {
            $this->assertInstanceOf(InvalidOperationException::class, $e);
        }

        try {
            yield $connection->readStreamEventsForwardAsync($s, 0, 1);
            $this->fail('No exception thrown on ReadStreamEventsForwardAsync');
        } catch (Throwable $e) {
            $this->assertInstanceOf(InvalidOperationException::class, $e);
        }

        try {
            yield $connection->readStreamEventsBackwardAsync($s, 0, 1);
            $this->fail('No exception thrown on ReadStreamEventsBackwardAsync');
        } catch (Throwable $e) {
            $this->assertInstanceOf(InvalidOperationException::class, $e);
        }

        try {
            yield $connection->readAllEventsForwardAsync(Position::start(), 1);
            $this->fail('No exception thrown on ReadAllEventsForwardAsync');
        } catch (Throwable $e) {
            $this->assertInstanceOf(InvalidOperationException::class, $e);
        }

        try {
            yield $connection->readAllEventsBackwardAsync(Position::end(), 1);
            $this->fail('No exception thrown on ReadAllEventsBackwardAsync');
        } catch (Throwable $e) {
            $this->assertInstanceOf(InvalidOperationException::class, $e);
        }

        try {
            yield $connection->startTransactionAsync($s, 0);
            $this->fail('No exception thrown on StartTransactionAsync');
        } catch (Throwable $e) {
            $this->assertInstanceOf(InvalidOperationException::class, $e);
        }

        try {
            yield $connection->subscribeToStreamAsync(
                $s,
                false,
                new class() implements EventAppearedOnSubscription {
                    public function __invoke(
                        EventStoreSubscription $subscription,
                        ResolvedEvent $resolvedEvent
                    ): Promise {
                        return new Success();
                    }
                }
            );

            $this->fail('No exception thrown on SubscribeToStreamAsync');
        } catch (Throwable $e) {
            $this->assertInstanceOf(InvalidOperationException::class, $e);
        }

        try {
            yield $connection->subscribeToAllAsync(
                false,
                new class() implements EventAppearedOnSubscription {
                    public function __invoke(
                        EventStoreSubscription $subscription,
                        ResolvedEvent $resolvedEvent
                    ): Promise {
                        return new Success();
                    }
                }
            );

            $this->fail('No exception thrown on SubscribeToAllAsync');
        } catch (Throwable $e) {
            $this->assertInstanceOf(InvalidOperationException::class, $e);
        }
    }
}
