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

use Amp\CancelledException;
use Amp\DeferredFuture;
use Amp\PHPUnit\AsyncTestCase;
use Amp\TimeoutCancellation;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventStorePersistentSubscription;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\Util\Guid;

class connect_to_existing_persistent_subscription_with_start_from_beginning_not_set_and_events_in_it extends AsyncTestCase
{
    use SpecificationWithConnection;

    private string $stream;

    private PersistentSubscriptionSettings $settings;

    private string $group = 'startinbeginning1';

    private DeferredFuture $resetEvent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stream = '$' . Guid::generateAsHex();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromCurrent()
            ->build();
        $this->resetEvent = new DeferredFuture();
    }

    protected function given(): void
    {
        $this->writeEvents();

        $this->connection->createPersistentSubscription(
            $this->stream,
            $this->group,
            $this->settings,
            DefaultData::adminCredentials()
        );

        $deferred = $this->resetEvent;

        $this->connection->connectToPersistentSubscription(
            $this->stream,
            $this->group,
            function (
                EventStorePersistentSubscription $subscription,
                ResolvedEvent $resolvedEvent,
                ?int $retryCount = null
            ) use ($deferred): void {
                if ($resolvedEvent->originalEventNumber() === 0) {
                    $deferred->complete(true);
                }
            },
            null,
            10,
            true,
            DefaultData::adminCredentials()
        );
    }

    private function writeEvents(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->connection->appendToStream(
                $this->stream,
                ExpectedVersion::Any,
                [new EventData(null, 'test', true, '{"foo":"bar"}')],
                DefaultData::adminCredentials()
            );
        }
    }

    protected function when(): void
    {
        $this->writeEvents();
    }

    /** @test */
    public function the_subscription_gets_no_events(): void
    {
        $this->expectException(CancelledException::class);

        $this->execute(function () {
            $this->resetEvent->getFuture()->await(new TimeoutCancellation(1));
        });
    }
}
