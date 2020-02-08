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

use Amp\Promise;
use Amp\Success;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Async\EventAppearedOnPersistentSubscription;
use Prooph\EventStore\Async\EventStorePersistentSubscription;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\Util\Guid;
use Throwable;

class connect_to_existing_persistent_subscription_with_permissions extends TestCase
{
    use SpecificationWithConnection;

    private Promise $sub;
    private string $stream;
    private PersistentSubscriptionSettings $settings;

    protected function setUp(): void
    {
        $this->stream = Guid::generateAsHex();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromCurrent()
            ->build();
    }

    protected function when(): Generator
    {
        yield $this->conn->createPersistentSubscriptionAsync(
            $this->stream,
            'agroupname17',
            $this->settings,
            DefaultData::adminCredentials()
        );

        $this->sub = $this->conn->connectToPersistentSubscriptionAsync(
            $this->stream,
            'agroupname17',
            new class() implements EventAppearedOnPersistentSubscription {
                public function __invoke(
                    EventStorePersistentSubscription $subscription,
                    ResolvedEvent $resolvedEvent,
                    ?int $retryCount = null
                ): Promise {
                    return new Success();
                }
            }
        );
    }

    /**
     * @test
     * @throws Throwable
     */
    public function the_subscription_suceeds(): void
    {
        $this->execute(function () {
            $this->assertNotNull(yield $this->sub);
        });
    }
}
