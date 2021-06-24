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

use Amp\Deferred;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use Generator;
use Prooph\EventStore\Async\EventStorePersistentSubscription;
use Prooph\EventStore\EventData;
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

    public const BUFFER_COUNT = 10;
    public const EVENT_WRITE_COUNT = self::BUFFER_COUNT * 2;

    private Deferred $eventsReceived;
    private int $eventReceivedCount = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->streamName = Guid::generateAsHex();
        $this->groupName = Guid::generateAsHex();
        $this->eventsReceived = new Deferred();
    }

    protected function when(): Generator
    {
        yield new Success();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function test(): Generator
    {
        yield $this->execute(function (): Generator {
            $settings = PersistentSubscriptionSettings::create()
                ->startFromCurrent()
                ->resolveLinkTos()
                ->build();

            yield $this->connection->createPersistentSubscriptionAsync(
                $this->streamName,
                $this->groupName,
                $settings,
                DefaultData::adminCredentials()
            );

            yield $this->connection->connectToPersistentSubscriptionAsync(
                $this->streamName,
                $this->groupName,
                function (
                    EventStorePersistentSubscription $subscription,
                    ResolvedEvent $resolvedEvent,
                    ?int $retryCount = null
                ): Promise {
                    $subscription->fail($resolvedEvent, PersistentSubscriptionNakEventAction::park(), 'fail');

                    if (++$this->eventReceivedCount === when_writing_and_subscribing_to_normal_events_manual_nack::EVENT_WRITE_COUNT) {
                        $this->eventsReceived->resolve(true);
                    }

                    return new Success();
                },
                null,
                10,
                false,
                DefaultData::adminCredentials()
            );

            for ($i = 0; $i < self::EVENT_WRITE_COUNT; $i++) {
                $eventData = new EventData(null, 'SomeEvent', false);

                yield $this->connection->appendToStreamAsync(
                    $this->streamName,
                    ExpectedVersion::ANY,
                    [$eventData],
                    DefaultData::adminCredentials()
                );
            }

            yield Promise\timeout($this->eventsReceived->promise(), 5000);
        });
    }
}
