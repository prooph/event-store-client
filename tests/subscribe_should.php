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
use Amp\Promise;
use Amp\Success;
use Amp\TimeoutException;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\EventStoreSubscription;
use Prooph\EventStoreClient\ExpectedVersion;
use ProophTest\EventStoreClient\Helper\Connection;
use ProophTest\EventStoreClient\Helper\TestEvent;
use function Amp\call;
use function Amp\Promise\timeout;
use function Amp\Promise\wait;

class subscribe_should extends TestCase
{
    private const Timeout = 5000;

    /** @test */
    public function be_able_to_subscribe_to_non_existing_stream_and_then_catch_new_event(): void
    {
        wait(call(function () {
            $stream = 'subscribe_should_be_able_to_subscribe_to_non_existing_stream_and_then_catch_created_event';

            $appeared = new Deferred();

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            yield $connection->subscribeToStreamAsync(
                $stream,
                false,
                function () use ($appeared): Promise {
                    $appeared->resolve(true);

                    return new Success();
                }
            );

            yield $connection->appendToStreamAsync($stream, ExpectedVersion::EmptyStream, [TestEvent::new()]);

            try {
                $result = yield timeout($appeared->promise(), self::Timeout);
                $this->assertTrue($result);
            } catch (TimeoutException $e) {
                $this->fail('Appeared countdown event timed out');
            }

            $connection->close();
        }));
    }

    /** @test */
    public function allow_multiple_subscriptions_to_same_stream(): void
    {
        wait(call(function () {
            $stream = 'subscribe_should_allow_multiple_subscriptions_to_same_stream';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            $appeared1 = new Deferred();
            $appeared2 = new Deferred();

            yield $connection->subscribeToStreamAsync(
                $stream,
                false,
                function () use ($appeared1): Promise {
                    $appeared1->resolve(true);

                    return new Success();
                }
            );

            yield $connection->subscribeToStreamAsync(
                $stream,
                false,
                function () use ($appeared2): Promise {
                    $appeared2->resolve(true);

                    return new Success();
                }
            );

            $connection->appendToStreamAsync($stream, ExpectedVersion::EmptyStream, [TestEvent::new()]);

            foreach ([$appeared1, $appeared2] as $appeared) {
                try {
                    $result = yield timeout($appeared->promise(), self::Timeout);
                    $this->assertTrue($result);
                } catch (TimeoutException $e) {
                    $this->fail('Appeared countdown event timed out');
                }
            }

            $connection->close();
        }));
    }

    /** @test */
    public function call_dropped_callback_after_unsubscribe_method_call(): void
    {
        wait(call(function () {
            $stream = 'subscribe_should_call_dropped_callback_after_unsubscribe_method_call';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            $dropped = new Deferred();

            /** @var EventStoreSubscription $subscription */
            $subscription = yield $connection->subscribeToStreamAsync(
                $stream,
                false,
                function (): Promise {
                    return new Success();
                },
                function () use ($dropped): void {
                    $dropped->resolve(true);
                }

            );

            $subscription->unsubscribe();

            try {
                $result = yield timeout($dropped->promise(), self::Timeout);
                $this->assertTrue($result);
            } catch (TimeoutException $e) {
                $this->fail('Dropdown countdown event timed out');
            }

            $connection->close();
        }));
    }

    /** @test */
    public function catch_deleted_events_as_well(): void
    {
        wait(call(function () {
            $stream = 'subscribe_should_catch_created_and_deleted_events_as_well';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            $appeared = new Deferred();

            /** @var EventStoreSubscription $subscription */
            $subscription = yield $connection->subscribeToStreamAsync(
                $stream,
                false,
                function () use ($appeared): Promise {
                    $appeared->resolve(true);

                    return new Success();
                }
            );

            yield $connection->deleteStreamAsync($stream, ExpectedVersion::EmptyStream, true);

            try {
                $result = yield timeout($appeared->promise(), self::Timeout);
                $this->assertTrue($result);
            } catch (TimeoutException $e) {
                $this->fail('Appeared countdown event timed out');
            }

            $connection->close();
        }));
    }
}
