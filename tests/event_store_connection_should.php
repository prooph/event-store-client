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

namespace ProophTest\EventStoreClient;

use Amp\Promise;
use Amp\Success;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\EventAppearedOnSubscription;
use Prooph\EventStoreClient\EventStoreSubscription;
use Prooph\EventStoreClient\Exception\InvalidOperationException;
use Prooph\EventStoreClient\Position;
use Prooph\EventStoreClient\ResolvedEvent;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;
use function Amp\call;
use function Amp\Promise\wait;

class event_store_connection_should extends TestCase
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
     * @throws Throwable
     */
    public function throw_invalid_operation_on_every_api_call_if_connect_was_not_called(): void
    {
        wait(call(function () {
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
        }));
    }
}
