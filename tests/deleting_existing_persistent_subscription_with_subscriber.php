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

use Amp\Deferred;
use Amp\Promise;
use Amp\Success;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Async\EventAppearedOnPersistentSubscription;
use Prooph\EventStore\Async\EventStorePersistentSubscription;
use Prooph\EventStore\Async\PersistentSubscriptionDropped;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SubscriptionDropReason;
use Prooph\EventStore\Util\Guid;
use Throwable;

class deleting_existing_persistent_subscription_with_subscriber extends TestCase
{
    use SpecificationWithConnection;

    private string $stream;
    private PersistentSubscriptionSettings $settings;
    private Deferred $called;

    protected function setUp(): void
    {
        $this->stream = Guid::generateAsHex();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromCurrent()
            ->build();
        $this->called = new Deferred();
    }

    protected function given(): Generator
    {
        yield $this->conn->createPersistentSubscriptionAsync(
            $this->stream,
            'groupname123',
            $this->settings,
            DefaultData::adminCredentials()
        );

        yield $this->conn->connectToPersistentSubscriptionAsync(
            $this->stream,
            'groupname123',
            new class() implements EventAppearedOnPersistentSubscription {
                public function __invoke(
                    EventStorePersistentSubscription $subscription,
                    ResolvedEvent $resolvedEvent,
                    ?int $retryCount = null
                ): Promise {
                    return new Success();
                }
            },
            new class($this->called) implements PersistentSubscriptionDropped {
                private $called;

                public function __construct(&$called)
                {
                    $this->called = &$called;
                }

                public function __invoke(
                    EventStorePersistentSubscription $subscription,
                    SubscriptionDropReason $reason,
                    ?Throwable $exception = null
                ): void {
                    $this->called->resolve(true);
                }
            }
        );
    }

    protected function when(): Generator
    {
        yield $this->conn->deletePersistentSubscriptionAsync(
            $this->stream,
            'groupname123',
            DefaultData::adminCredentials()
        );
    }

    /**
     * @test
     * @throws Throwable
     */
    public function the_subscription_is_dropped(): void
    {
        $this->execute(function () {
            $value = yield Promise\timeout($this->called->promise(), 5000);
            $this->assertTrue($value);
        });
    }
}
