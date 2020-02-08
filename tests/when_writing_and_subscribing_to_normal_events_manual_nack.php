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
use Prooph\EventStore\EventData;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\PersistentSubscriptionNakEventAction;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\Util\Guid;
use Throwable;

class when_writing_and_subscribing_to_normal_events_manual_nack extends TestCase
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
     * @throws Throwable
     */
    public function test(): void
    {
        $this->execute(function () {
            $settings = PersistentSubscriptionSettings::create()
                ->startFromCurrent()
                ->resolveLinkTos()
                ->build();

            yield $this->conn->createPersistentSubscriptionAsync(
                $this->streamName,
                $this->groupName,
                $settings,
                DefaultData::adminCredentials()
            );

            yield $this->conn->connectToPersistentSubscriptionAsync(
                $this->streamName,
                $this->groupName,
                new class($this->eventReceivedCount, $this->eventsReceived) implements EventAppearedOnPersistentSubscription {
                    private int $eventReceivedCount;
                    private Deferred $eventsReceived;

                    public function __construct(int &$eventReceivedCount, Deferred $eventsReceived)
                    {
                        $this->eventReceivedCount = &$eventReceivedCount;
                        $this->eventsReceived = $eventsReceived;
                    }

                    public function __invoke(
                        EventStorePersistentSubscription $subscription,
                        ResolvedEvent $resolvedEvent,
                        ?int $retryCount = null
                    ): Promise {
                        $subscription->fail($resolvedEvent, PersistentSubscriptionNakEventAction::park(), 'fail');

                        if (++$this->eventReceivedCount === when_writing_and_subscribing_to_normal_events_manual_nack::EVENT_WRITE_COUNT) {
                            $this->eventsReceived->resolve(true);
                        }

                        return new Success();
                    }
                },
                null,
                10,
                false,
                DefaultData::adminCredentials()
            );

            for ($i = 0; $i < self::EVENT_WRITE_COUNT; $i++) {
                $eventData = new EventData(null, 'SomeEvent', false);

                yield $this->conn->appendToStreamAsync(
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
