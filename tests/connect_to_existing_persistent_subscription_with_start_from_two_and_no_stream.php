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
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\Util\Guid;

class connect_to_existing_persistent_subscription_with_start_from_two_and_no_stream extends AsyncTestCase
{
    use SpecificationWithConnection;

    private string $stream;

    private PersistentSubscriptionSettings $settings;

    private string $group = 'startinbeginning1';

    private ?ResolvedEvent $firstEvent;

    private DeferredFuture $resetEvent;

    private EventId $eventId;

    private bool $set = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventId = EventId::generate();
        $this->stream = '$' . Guid::generateAsHex();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFrom(2)
            ->build();
        $this->resetEvent = new DeferredFuture();
    }

    protected function given(): void
    {
        $this->connection->createPersistentSubscription(
            $this->stream,
            $this->group,
            $this->settings,
            DefaultData::adminCredentials()
        );

        $this->connection->connectToPersistentSubscription(
            $this->stream,
            $this->group,
            function (
                EventStorePersistentSubscription $subscription,
                ResolvedEvent $resolvedEvent,
                ?int $retryCount = null
            ): void {
                if (! $this->set) {
                    $this->set = true;
                    $this->firstEvent = $resolvedEvent;
                    $this->resetEvent->complete(true);
                }
            },
            null,
            10,
            true,
            DefaultData::adminCredentials()
        );
    }

    protected function when(): void
    {
        $this->connection->appendToStream(
            $this->stream,
            ExpectedVersion::Any,
            [new EventData(null, 'test', true, '{"foo":"bar"}')],
            DefaultData::adminCredentials()
        );

        $this->connection->appendToStream(
            $this->stream,
            ExpectedVersion::Any,
            [new EventData(null, 'test', true, '{"foo":"bar"}')],
            DefaultData::adminCredentials()
        );

        $this->connection->appendToStream(
            $this->stream,
            ExpectedVersion::Any,
            [new EventData($this->eventId, 'test', true, '{"foo":"bar"}')],
            DefaultData::adminCredentials()
        );
    }

    /** @test */
    public function the_subscription_gets_event_two_as_its_first_event(): void
    {
        $this->execute(function (): void {
            $value = $this->resetEvent->getFuture()->await(new TimeoutCancellation(10));
            $this->assertTrue($value);
            $this->assertSame(2, $this->firstEvent->originalEventNumber());
            $this->assertTrue($this->firstEvent->originalEvent()->eventId()->equals($this->eventId));
        });
    }
}
