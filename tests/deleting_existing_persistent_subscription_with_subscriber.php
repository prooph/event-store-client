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

use Amp\Deferred;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use Generator;
use Prooph\EventStore\Async\EventStorePersistentSubscription;
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
    private Deferred $called;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stream = Guid::generateAsHex();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromCurrent()
            ->build();
        $this->called = new Deferred();
    }

    protected function given(): Generator
    {
        yield $this->connection->createPersistentSubscriptionAsync(
            $this->stream,
            'groupname123',
            $this->settings,
            DefaultData::adminCredentials()
        );

        yield $this->connection->connectToPersistentSubscriptionAsync(
            $this->stream,
            'groupname123',
            function (
                EventStorePersistentSubscription $subscription,
                ResolvedEvent $resolvedEvent,
                ?int $retryCount = null
            ): Promise {
                return new Success();
            },
            function (
                EventStorePersistentSubscription $subscription,
                SubscriptionDropReason $reason,
                ?Throwable $exception = null
            ): void {
                $this->called->resolve(true);
            }
        );
    }

    protected function when(): Generator
    {
        yield $this->connection->deletePersistentSubscriptionAsync(
            $this->stream,
            'groupname123',
            DefaultData::adminCredentials()
        );
    }

    /** @test */
    public function the_subscription_is_dropped(): Generator
    {
        yield $this->execute(function (): Generator {
            $value = yield Promise\timeout($this->called->promise(), 5000);
            $this->assertTrue($value);
        });
    }
}
