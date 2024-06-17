<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2024 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2024 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Closure;
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
    private const Timeout = 10;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection->setStreamMetadata(
            '$all',
            ExpectedVersion::Any,
            StreamMetadata::create()->setReadRoles(SystemRoles::All)->build(),
            new UserCredentials(SystemUsers::Admin, SystemUsers::DefaultAdminPassword)
        );
    }

    protected function tearDown(): void
    {
        $this->connection->setStreamMetadata(
            '$all',
            ExpectedVersion::Any,
            new StreamMetadata(),
            new UserCredentials(SystemUsers::Admin, SystemUsers::DefaultAdminPassword)
        );

        parent::tearDown();
    }

    /** @test */
    public function allow_multiple_subscriptions(): void
    {
        $stream = 'subscribe_to_all_should_allow_multiple_subscriptions';

        $appeared = new CountdownEvent(2);
        $dropped = new CountdownEvent(2);

        $this->connection->subscribeToAll(
            false,
            $this->appearedWithCountdown($appeared),
            $this->droppedWithCountdown($dropped)
        );

        $this->connection->subscribeToAll(
            false,
            $this->appearedWithCountdown($appeared),
            $this->droppedWithCountdown($dropped)
        );

        $this->connection->appendToStream(
            $stream,
            ExpectedVersion::NoStream,
            [TestEvent::newTestEvent()]
        );

        $this->assertTrue($appeared->wait(self::Timeout), 'Appeared countdown event timed out');
    }

    /** @test */
    public function catch_deleted_events_as_well(): void
    {
        $stream = 'subscribe_to_all_should_catch_created_and_deleted_events_as_well';

        $appeared = new CountdownEvent(1);
        $dropped = new CountdownEvent(1);

        $this->connection->subscribeToAll(
            false,
            $this->appearedWithCountdown($appeared),
            $this->droppedWithCountdown($dropped)
        );

        $this->connection->deleteStream($stream, ExpectedVersion::NoStream, true);

        $this->assertTrue($appeared->wait(self::Timeout), 'Appeared countdown event didn\'t fire in time');
    }

    private function appearedWithCountdown(CountdownEvent $appeared): Closure
    {
        return function (
            EventStoreSubscription $subscription,
            ResolvedEvent $resolvedEvent
        ) use ($appeared): void {
            $appeared->signal();
        };
    }

    private function droppedWithCountdown(CountdownEvent $dropped): Closure
    {
        return function (
            EventStoreSubscription $subscription,
            SubscriptionDropReason $reason,
            ?Throwable $exception = null
        ) use ($dropped): void {
            $dropped->signal();
        };
    }
}
