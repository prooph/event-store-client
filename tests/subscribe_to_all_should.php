<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2019 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2019 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use function Amp\call;
use Amp\Promise;
use function Amp\Promise\timeout;
use Amp\Success;
use Amp\TimeoutException;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Async\EventAppearedOnSubscription;
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\EventStoreSubscription;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\SubscriptionDropped;
use Prooph\EventStore\SubscriptionDropReason;
use Prooph\EventStore\UserCredentials;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;

class subscribe_to_all_should extends TestCase
{
    private const TIMEOUT = 10000;

    /**
     * @throws Throwable
     */
    private function execute(callable $function): void
    {
        Promise\wait(call(function () use ($function) {
            $connection = TestConnection::create();

            yield $connection->connectAsync();

            yield $connection->setStreamMetadataAsync(
                '$all',
                ExpectedVersion::ANY,
                StreamMetadata::create()->setReadRoles(SystemRoles::ALL)->build(),
                new UserCredentials(SystemUsers::ADMIN, SystemUsers::DEFAULT_ADMIN_PASSWORD)
            );

            yield from $function();

            yield $connection->setStreamMetadataAsync(
                '$all',
                ExpectedVersion::ANY,
                new StreamMetadata(),
                new UserCredentials(SystemUsers::ADMIN, SystemUsers::DEFAULT_ADMIN_PASSWORD)

            );

            $connection->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function allow_multiple_subscriptions(): void
    {
        $this->execute(function () {
            $stream = 'subscribe_to_all_should_allow_multiple_subscriptions';

            $store = TestConnection::create();

            yield $store->connectAsync();

            $appeared = new CountdownEvent(2);
            $dropped = new CountdownEvent(2);

            yield $store->subscribeToAllAsync(
                false,
                $this->appearedWithCountdown($appeared),
                $this->droppedWithCountdown($dropped)
            );

            yield $store->subscribeToAllAsync(
                false,
                $this->appearedWithCountdown($appeared),
                $this->droppedWithCountdown($dropped)
            );

            $create = $store->appendToStreamAsync(
                $stream,
                ExpectedVersion::NO_STREAM,
                [TestEvent::newTestEvent()]
            );

            try {
                yield Promise\timeout($create, self::TIMEOUT);
            } catch (TimeoutException $e) {
                $this->fail('StreamCreateAsync timed out');

                return;
            }

            $this->assertTrue(yield $appeared->wait(self::TIMEOUT), 'Appeared countdown event timed out');
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function catch_deleted_events_as_well(): void
    {
        $this->execute(function () {
            $stream = 'subscribe_to_all_should_catch_created_and_deleted_events_as_well';

            $store = TestConnection::create();

            yield $store->connectAsync();

            $appeared = new CountdownEvent(1);
            $dropped = new CountdownEvent(1);

            yield $store->subscribeToAllAsync(
                false,
                $this->appearedWithCountdown($appeared),
                $this->droppedWithCountdown($dropped)
            );

            $delete = $store->deleteStreamAsync($stream, ExpectedVersion::NO_STREAM, true);

            try {
                yield Promise\timeout($delete, self::TIMEOUT);
            } catch (TimeoutException $e) {
                $this->fail('DeleteStreamAsync timed out');

                return;
            }

            $this->assertTrue(yield $appeared->wait(self::TIMEOUT), 'Appeared countdown event didn\'t fire in time');
        });
    }

    private function appearedWithCountdown(CountdownEvent $appeared): EventAppearedOnSubscription
    {
        return new class($appeared) implements EventAppearedOnSubscription {
            /** @var CountdownEvent */
            private $appeared;

            public function __construct(CountdownEvent $appeared)
            {
                $this->appeared = $appeared;
            }

            public function __invoke(
                EventStoreSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ): Promise {
                $this->appeared->signal();

                return new Success();
            }
        };
    }

    private function droppedWithCountdown(CountdownEvent $dropped): SubscriptionDropped
    {
        return new class($dropped) implements SubscriptionDropped {
            /** @var CountdownEvent */
            private $dropped;

            public function __construct(CountdownEvent $dropped)
            {
                $this->dropped = $dropped;
            }

            public function __invoke(
                EventStoreSubscription $subscription,
                SubscriptionDropReason $reason,
                ?Throwable $exception = null
            ): void {
                $this->dropped->signal();
            }
        };
    }
}
