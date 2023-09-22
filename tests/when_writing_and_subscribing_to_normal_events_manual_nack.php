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
use Prooph\EventStore\PersistentSubscriptionNakEventAction;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\Util\Guid;

class when_writing_and_subscribing_to_normal_events_manual_nack extends AsyncTestCase
{
    use SpecificationWithConnection;

    private string $streamName;

    private string $groupName;

    public const BufferCount = 10;

    public const EventWriteCount = self::BufferCount * 2;

    private DeferredFuture $eventsReceived;

    private int $eventReceivedCount = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->streamName = Guid::generateAsHex();
        $this->groupName = Guid::generateAsHex();
        $this->eventsReceived = new DeferredFuture();
    }

    protected function when(): void
    {
    }

    /**
     * @doesNotPerformAssertions
     */
    public function test(): void
    {
        $this->execute(function (): void {
            $settings = PersistentSubscriptionSettings::create()
                ->startFromCurrent()
                ->resolveLinkTos()
                ->build();

            $this->connection->createPersistentSubscription(
                $this->streamName,
                $this->groupName,
                $settings,
                DefaultData::adminCredentials()
            );

            $this->connection->connectToPersistentSubscription(
                $this->streamName,
                $this->groupName,
                function (
                    EventStorePersistentSubscription $subscription,
                    ResolvedEvent $resolvedEvent,
                    ?int $retryCount = null
                ): void {
                    $subscription->fail($resolvedEvent, PersistentSubscriptionNakEventAction::Park, 'fail');

                    if (++$this->eventReceivedCount === when_writing_and_subscribing_to_normal_events_manual_nack::EventWriteCount) {
                        $this->eventsReceived->complete(true);
                    }
                },
                null,
                10,
                false,
                DefaultData::adminCredentials()
            );

            for ($i = 0; $i < self::EventWriteCount; $i++) {
                $eventData = new EventData(null, 'SomeEvent', false);

                $this->connection->appendToStream(
                    $this->streamName,
                    ExpectedVersion::Any,
                    [$eventData],
                    DefaultData::adminCredentials()
                );
            }

            $this->eventsReceived->getFuture()->await(new TimeoutCancellation(5));
        });
    }
}
