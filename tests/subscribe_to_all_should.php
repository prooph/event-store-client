<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2021 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2021 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\Promise;
use Amp\Success;
use Amp\TimeoutException;
use Closure;
use Generator;
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\EventStoreSubscription;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\SubscriptionDropReason;
use Prooph\EventStore\UserCredentials;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;

class subscribe_to_all_should extends EventStoreConnectionTestCase
{
    private const TIMEOUT = 10000;

    protected function setUpAsync(): Generator
    {
        yield from parent::setUpAsync();

        yield $this->connection->setStreamMetadataAsync(
            '$all',
            ExpectedVersion::ANY,
            StreamMetadata::create()->setReadRoles(SystemRoles::ALL)->build(),
            new UserCredentials(SystemUsers::ADMIN, SystemUsers::DEFAULT_ADMIN_PASSWORD)
        );
    }

    protected function tearDownAsync(): Generator
    {
        yield $this->connection->setStreamMetadataAsync(
            '$all',
            ExpectedVersion::ANY,
            new StreamMetadata(),
            new UserCredentials(SystemUsers::ADMIN, SystemUsers::DEFAULT_ADMIN_PASSWORD)
        );

        yield from parent::tearDownAsync();
    }

    /** @test */
    public function allow_multiple_subscriptions(): Generator
    {
        $stream = 'subscribe_to_all_should_allow_multiple_subscriptions';

        $appeared = new CountdownEvent(2);
        $dropped = new CountdownEvent(2);

        yield $this->connection->subscribeToAllAsync(
            false,
            $this->appearedWithCountdown($appeared),
            $this->droppedWithCountdown($dropped)
        );

        yield $this->connection->subscribeToAllAsync(
            false,
            $this->appearedWithCountdown($appeared),
            $this->droppedWithCountdown($dropped)
        );

        $create = $this->connection->appendToStreamAsync(
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
    }

    /** @test */
    public function catch_deleted_events_as_well(): Generator
    {
        $stream = 'subscribe_to_all_should_catch_created_and_deleted_events_as_well';

        $appeared = new CountdownEvent(1);
        $dropped = new CountdownEvent(1);

        yield $this->connection->subscribeToAllAsync(
            false,
            $this->appearedWithCountdown($appeared),
            $this->droppedWithCountdown($dropped)
        );

        $delete = $this->connection->deleteStreamAsync($stream, ExpectedVersion::NO_STREAM, true);

        try {
            yield Promise\timeout($delete, self::TIMEOUT);
        } catch (TimeoutException $e) {
            $this->fail('DeleteStreamAsync timed out');

            return;
        }

        $this->assertTrue(yield $appeared->wait(self::TIMEOUT), 'Appeared countdown event didn\'t fire in time');
    }

    private function appearedWithCountdown(CountdownEvent $appeared): Closure
    {
        return function (
            EventStoreSubscription $subscription,
            ResolvedEvent $resolvedEvent
        ) use ($appeared): Promise {
            $appeared->signal();

            return new Success();
        };
    }

    private function droppedWithCountdown(CountdownEvent $dropped): Closure
    {
        return function (
            EventStoreSubscription $subscription,
            SubscriptionDropReason $reason,
            ?Throwable $exception = null
        ): void {
            $dropped->signal();
        };
    }
}
