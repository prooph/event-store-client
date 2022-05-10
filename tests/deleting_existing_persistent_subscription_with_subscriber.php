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

use Amp\DeferredFuture;
use Amp\PHPUnit\AsyncTestCase;
use Amp\TimeoutCancellation;
use Prooph\EventStore\EventStorePersistentSubscription;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SubscriptionDropReason;
use Prooph\EventStore\Util\Guid;
use Throwable;

class deleting_existing_persistent_subscription_with_subscriber extends AsyncTestCase
{
    use SpecificationWithConnection;

    private string $stream;

    private PersistentSubscriptionSettings $settings;

    private DeferredFuture $called;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stream = Guid::generateAsHex();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromCurrent()
            ->build();
        $this->called = new DeferredFuture();
    }

    protected function given(): void
    {
        $this->connection->createPersistentSubscription(
            $this->stream,
            'groupname123',
            $this->settings,
            DefaultData::adminCredentials()
        );

        $this->connection->connectToPersistentSubscription(
            $this->stream,
            'groupname123',
            function (
                EventStorePersistentSubscription $subscription,
                ResolvedEvent $resolvedEvent,
                ?int $retryCount = null
            ): void {
            },
            function (
                EventStorePersistentSubscription $subscription,
                SubscriptionDropReason $reason,
                ?Throwable $exception = null
            ): void {
                $this->called->complete(true);
            }
        );
    }

    protected function when(): void
    {
        $this->connection->deletePersistentSubscription(
            $this->stream,
            'groupname123',
            DefaultData::adminCredentials()
        );
    }

    /** @test */
    public function the_subscription_is_dropped(): void
    {
        $this->execute(function (): void {
            $value = $this->called->getFuture()->await(new TimeoutCancellation(5));
            $this->assertTrue($value);
        });
    }
}
