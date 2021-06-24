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

use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use Generator;
use Prooph\EventStore\Async\EventStorePersistentSubscription;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\Util\Guid;

class connect_to_existing_persistent_subscription_with_permissions extends AsyncTestCase
{
    use SpecificationWithConnection;

    private Promise $sub;
    private string $stream;
    private PersistentSubscriptionSettings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stream = Guid::generateAsHex();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromCurrent()
            ->build();
    }

    protected function when(): Generator
    {
        yield $this->connection->createPersistentSubscriptionAsync(
            $this->stream,
            'agroupname17',
            $this->settings,
            DefaultData::adminCredentials()
        );

        $this->sub = $this->connection->connectToPersistentSubscriptionAsync(
            $this->stream,
            'agroupname17',
            fn (
                EventStorePersistentSubscription $subscription,
                ResolvedEvent $resolvedEvent,
                ?int $retryCount = null
            ): Promise => new Success()
        );
    }

    /** @test */
    public function the_subscription_suceeds(): Generator
    {
        yield $this->execute(function (): Generator {
            $this->assertNotNull(yield $this->sub);
        });
    }
}
