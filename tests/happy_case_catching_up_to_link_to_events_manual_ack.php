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
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use Amp\TimeoutException;
use Generator;
use Prooph\EventStore\Async\EventAppearedOnPersistentSubscription;
use Prooph\EventStore\Async\EventStorePersistentSubscription;
use Prooph\EventStore\Common\SystemEventTypes;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\Util\Guid;

class happy_case_catching_up_to_link_to_events_manual_ack extends AsyncTestCase
{
    use SpecificationWithConnection;

    private string $streamName;
    private string $groupName;

    private const BUFFER_COUNT = 10;
    private const EVENT_WRITE_COUNT = self::BUFFER_COUNT * 2;

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
     * @test
     */
    public function test(): Generator
    {
        yield $this->execute(function (): Generator {
            $settings = PersistentSubscriptionSettings::create()
                ->startFromBeginning()
                ->resolveLinkTos()
                ->build();

            for ($i = 0; $i < self::EVENT_WRITE_COUNT; $i++) {
                $eventData = new EventData(EventId::generate(), 'SomeEvent', false, '', '');

                yield $this->conn->appendToStreamAsync(
                    $this->streamName . 'original',
                    ExpectedVersion::ANY,
                    [$eventData],
                    DefaultData::adminCredentials()
                );
            }

            yield $this->conn->createPersistentSubscriptionAsync(
                $this->streamName,
                $this->groupName,
                $settings,
                DefaultData::adminCredentials()
            );

            yield $this->conn->connectToPersistentSubscriptionAsync(
                $this->streamName,
                $this->groupName,
                new class($this->eventsReceived, $this->eventReceivedCount, self::EVENT_WRITE_COUNT) implements EventAppearedOnPersistentSubscription {
                    private Deferred $eventsReceived;
                    private $eventReceivedCount;
                    private int $eventWriteCount;

                    public function __construct(Deferred $eventsReceived, &$eventReceivedCount, int $eventWriteCount)
                    {
                        $this->eventsReceived = $eventsReceived;
                        $this->eventReceivedCount = &$eventReceivedCount;
                        $this->eventWriteCount = $eventWriteCount;
                    }

                    public function __invoke(
                        EventStorePersistentSubscription $subscription,
                        ResolvedEvent $resolvedEvent,
                        ?int $retryCount = null
                    ): Promise {
                        $subscription->acknowledge($resolvedEvent);
                        $this->eventReceivedCount++;

                        if (++$this->eventReceivedCount === $this->eventWriteCount) {
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
                $eventData = new EventData(
                    null,
                    SystemEventTypes::LINK_TO,
                    false,
                    $i . '@' . $this->streamName . 'original'
                );

                yield $this->conn->appendToStreamAsync(
                    $this->streamName,
                    ExpectedVersion::ANY,
                    [$eventData],
                    DefaultData::adminCredentials()
                );
            }

            try {
                $result = yield Promise\timeout($this->eventsReceived->promise(), 5000);

                $this->assertTrue($result);
            } catch (TimeoutException $e) {
                $this->fail('Timed out waiting for events');
            }
        });
    }
}
