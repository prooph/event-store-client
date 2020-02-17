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
use Amp\Promise;
use function Amp\Promise\timeout;
use function Amp\Promise\wait;
use Amp\Success;
use Amp\TimeoutException;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Async\EventAppearedOnSubscription;
use Prooph\EventStore\EventStoreSubscription;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SubscriptionDropped;
use Prooph\EventStore\SubscriptionDropReason;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;

class subscribe_should extends TestCase
{
    private const TIMEOUT = 10000;

    /**
     * @test
     * @throws Throwable
     */
    public function be_able_to_subscribe_to_non_existing_stream_and_then_catch_new_event(): void
    {
        wait(call(function () {
            $stream = 'subscribe_should_be_able_to_subscribe_to_non_existing_stream_and_then_catch_created_event';

            $appeared = new Deferred();

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            yield $connection->subscribeToStreamAsync(
                $stream,
                false,
                $this->eventAppearedResolver($appeared)
            );

            yield $connection->appendToStreamAsync($stream, ExpectedVersion::NO_STREAM, [TestEvent::newTestEvent()]);

            try {
                $result = yield timeout($appeared->promise(), self::TIMEOUT);
                $this->assertTrue($result);
            } catch (TimeoutException $e) {
                $this->fail('Appeared countdown event timed out');
            }

            $connection->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function allow_multiple_subscriptions_to_same_stream(): void
    {
        wait(call(function () {
            $stream = 'subscribe_should_allow_multiple_subscriptions_to_same_stream';

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            $appeared1 = new Deferred();
            $appeared2 = new Deferred();

            yield $connection->subscribeToStreamAsync(
                $stream,
                false,
                $this->eventAppearedResolver($appeared1)
            );

            yield $connection->subscribeToStreamAsync(
                $stream,
                false,
                $this->eventAppearedResolver($appeared2)
            );

            $connection->appendToStreamAsync($stream, ExpectedVersion::NO_STREAM, [TestEvent::newTestEvent()]);

            try {
                $result = yield timeout($appeared1->promise(), self::TIMEOUT);
                $this->assertTrue($result);
            } catch (TimeoutException $e) {
                $this->fail('Appeared1 countdown event timed out');
            }

            try {
                $result = yield timeout($appeared2->promise(), self::TIMEOUT);
                $this->assertTrue($result);
            } catch (TimeoutException $e) {
                $this->fail('Appeared2 countdown event timed out');
            }

            $connection->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function call_dropped_callback_after_unsubscribe_method_call(): void
    {
        wait(call(function () {
            $stream = 'subscribe_should_call_dropped_callback_after_unsubscribe_method_call';

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            $dropped = new Deferred();

            $subscription = yield $connection->subscribeToStreamAsync(
                $stream,
                false,
                new class() implements EventAppearedOnSubscription {
                    public function __invoke(
                        EventStoreSubscription $subscription,
                        ResolvedEvent $resolvedEvent
                    ): Promise {
                        return new Success();
                    }
                },
                $this->subscriptionDroppedResolver($dropped)
            );
            \assert($subscription instanceof EventStoreSubscription);

            $subscription->unsubscribe();

            try {
                $result = yield timeout($dropped->promise(), self::TIMEOUT);
                $this->assertTrue($result);
            } catch (TimeoutException $e) {
                $this->fail('Dropdown countdown event timed out');
            }

            $connection->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function catch_deleted_events_as_well(): void
    {
        wait(call(function () {
            $stream = 'subscribe_should_catch_created_and_deleted_events_as_well';

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            $appeared = new Deferred();

            yield $connection->subscribeToStreamAsync(
                $stream,
                false,
                $this->eventAppearedResolver($appeared)
            );

            yield $connection->deleteStreamAsync($stream, ExpectedVersion::NO_STREAM, true);

            try {
                $result = yield timeout($appeared->promise(), self::TIMEOUT);
                $this->assertTrue($result);
            } catch (TimeoutException $e) {
                $this->fail('Appeared countdown event timed out');
            }

            $connection->close();
        }));
    }

    private function eventAppearedResolver(Deferred $deferred): EventAppearedOnSubscription
    {
        return new class($deferred) implements EventAppearedOnSubscription {
            private Deferred $deferred;

            public function __construct(Deferred $deferred)
            {
                $this->deferred = $deferred;
            }

            public function __invoke(
                EventStoreSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ): Promise {
                $this->deferred->resolve(true);

                return new Success();
            }
        };
    }

    private function subscriptionDroppedResolver(Deferred $deferred): SubscriptionDropped
    {
        return new class($deferred) implements SubscriptionDropped {
            private Deferred $deferred;

            public function __construct(Deferred $deferred)
            {
                $this->deferred = $deferred;
            }

            public function __invoke(
                EventStoreSubscription $subscription,
                SubscriptionDropReason $reason,
                ?Throwable $exception = null
            ): void {
                $this->deferred->resolve(true);
            }
        };
    }
}
