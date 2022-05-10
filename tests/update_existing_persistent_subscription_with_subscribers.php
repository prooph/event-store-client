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
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventStorePersistentSubscription;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SubscriptionDropReason;
use Prooph\EventStore\Util\Guid;
use Throwable;

class update_existing_persistent_subscription_with_subscribers extends AsyncTestCase
{
    use SpecificationWithConnection;

    private string $stream;

    private PersistentSubscriptionSettings $settings;

    private DeferredFuture $dropped;

    private ?SubscriptionDropReason $reason;

    private ?Throwable $exception;

    private ?Throwable $caught = null;

    protected function given(): void
    {
        $this->stream = Guid::generateAsHex();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromCurrent()->build();
        $this->dropped = new DeferredFuture();

        $this->connection->appendToStream(
            $this->stream,
            ExpectedVersion::Any,
            [new EventData(null, 'whatever', true, '{"foo": 2}')]
        );

        $this->connection->createPersistentSubscription(
            $this->stream,
            'existing',
            $this->settings,
            DefaultData::adminCredentials()
        );

        $this->connection->connectToPersistentSubscription(
            $this->stream,
            'existing',
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
                $this->reason = $reason;
                $this->exception = $exception;
                $this->dropped->complete(true);
            }
        );
    }

    protected function when(): void
    {
        try {
            $this->connection->updatePersistentSubscription(
                $this->stream,
                'existing',
                $this->settings,
                DefaultData::adminCredentials()
            );
        } catch (Throwable $ex) {
            $this->caught = $ex;
        }
    }

    /** @test */
    public function the_completion_succeeds(): void
    {
        $this->execute(function (): void {
            $this->assertNull($this->caught);
        });
    }

    /** @test */
    public function existing_subscriptions_are_dropped(): void
    {
        $this->execute(function (): void {
            $this->assertTrue($this->dropped->getFuture()->await(new TimeoutCancellation(5)));
            $this->assertInstanceOf(SubscriptionDropReason::class, $this->reason);
            $this->assertSame(SubscriptionDropReason::UserInitiated, $this->reason);
            $this->assertNull($this->exception);
        });
    }
}
