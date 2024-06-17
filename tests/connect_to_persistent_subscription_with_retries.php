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

use Amp\DeferredFuture;
use Amp\PHPUnit\AsyncTestCase;
use Amp\TimeoutCancellation;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\EventStorePersistentSubscription;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\PersistentSubscriptionNakEventAction;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\Util\Guid;

class connect_to_persistent_subscription_with_retries extends AsyncTestCase
{
    use SpecificationWithConnection;

    private string $stream;

    private PersistentSubscriptionSettings $settings;

    private DeferredFuture $resetEvent;

    private EventId $eventId;

    private ?int $retryCount;

    private string $group = 'retries';

    protected function setUp(): void
    {
        parent::setUp();

        $this->stream = Guid::generateAsHex();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromBeginning()
            ->build();
        $this->resetEvent = new DeferredFuture();
        $this->eventId = EventId::generate();
    }

    protected function given(): void
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
                if ($retryCount > 4) {
                    $this->retryCount = $retryCount;
                    $subscription->acknowledge($resolvedEvent);
                    $this->resetEvent->complete(true);
                } else {
                    $subscription->fail(
                        $resolvedEvent,
                        PersistentSubscriptionNakEventAction::Retry,
                        'Not yet tried enough times'
                    );
                }
            },
            null,
            10,
            false,
            DefaultData::adminCredentials()
        );
    }

    protected function when(): void
    {
        $this->connection->appendToStream(
            $this->stream,
            ExpectedVersion::Any,
            [new EventData($this->eventId, 'test', true, '{"foo":"bar"}')],
            DefaultData::adminCredentials()
        );
    }

    /** @test */
    public function events_are_retried_until_success(): void
    {
        $this->execute(function (): void {
            $value = $this->resetEvent->getFuture()->await(new TimeoutCancellation(10));
            $this->assertTrue($value);
            $this->assertSame(5, $this->retryCount);
        });
    }
}
