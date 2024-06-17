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

use Amp\PHPUnit\AsyncTestCase;
use Prooph\EventStore\EventStorePersistentSubscription;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\Util\Guid;

class connect_to_existing_persistent_subscription_without_permissions extends AsyncTestCase
{
    use SpecificationWithConnection;

    private string $stream;

    private PersistentSubscriptionSettings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stream = '$' . Guid::generateAsHex();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromCurrent()
            ->build();
    }

    protected function when(): void
    {
        $this->connection->createPersistentSubscription(
            $this->stream,
            'agroupname55',
            $this->settings,
            DefaultData::adminCredentials()
        );

        $this->connection->connectToPersistentSubscription(
            $this->stream,
            'agroupname55',
            function (
                EventStorePersistentSubscription $subscription,
                ResolvedEvent $resolvedEvent,
                ?int $retryCount = null
            ): void {
            }
        );
    }

    /** @test */
    public function the_subscription_fails_to_connect_with_access_denied_exception(): void
    {
        $this->expectException(AccessDenied::class);

        $this->execute(function (): void {
        });
    }
}
