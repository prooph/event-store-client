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
use Prooph\EventStore\EventStorePersistentSubscription;
use Prooph\EventStore\Exception\MaximumSubscribersReached;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\Util\Guid;
use Throwable;

class connect_to_existing_persistent_subscription_with_max_one_client extends AsyncTestCase
{
    use SpecificationWithConnection;

    private string $stream;

    private PersistentSubscriptionSettings $settings;

    private Throwable $exception;

    private string $group = 'startinbeginning1';

    private ?EventStorePersistentSubscription $firstSubscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stream = '$' . Guid::generateAsHex();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromCurrent()
            ->withMaxSubscriberCountOf(1)
            ->build();
    }

    protected function given(): void
    {
        $this->connection->createPersistentSubscription(
            $this->stream,
            $this->group,
            $this->settings,
            DefaultData::adminCredentials()
        );

        $this->firstSubscription = $this->connection->connectToPersistentSubscription(
            $this->stream,
            $this->group,
            function (
                EventStorePersistentSubscription $subscription,
                ResolvedEvent $resolvedEvent,
                ?int $retryCount = null
            ): void {
                $subscription->acknowledge($resolvedEvent);
            },
            null,
            10,
            false,
            DefaultData::adminCredentials()
        );
    }

    protected function when(): void
    {
        try {
            $this->connection->connectToPersistentSubscription(
                $this->stream,
                $this->group,
                function (
                    EventStorePersistentSubscription $subscription,
                    ResolvedEvent $resolvedEvent,
                    ?int $retryCount = null
                ): void {
                    $subscription->acknowledge($resolvedEvent);
                },
                null,
                10,
                false,
                DefaultData::adminCredentials()
            );
        } catch (Throwable $e) {
            $this->exception = $e;
        }
    }

    /** @test */
    public function the_first_subscription_connects_successfully(): void
    {
        $this->execute(function (): void {
            $this->assertNotNull($this->firstSubscription);
        });
    }

    /** @test */
    public function the_second_subscription_throws_maximum_subscribers_reached_exception(): void
    {
        $this->execute(function (): void {
            $this->assertInstanceOf(MaximumSubscribersReached::class, $this->exception);
        });
    }
}
