<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\Deferred;
use Amp\Promise;
use Amp\Success;
use Amp\TimeoutException;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\AsyncEventStorePersistentSubscription;
use Prooph\EventStore\EventAppearedOnAsyncPersistentSubscription;
use Prooph\EventStore\EventData;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\Util\Guid;
use Throwable;
use function Amp\Promise\timeout;

class happy_case_writing_and_subscribing_to_normal_events_manual_ack extends TestCase
{
    use SpecificationWithConnection;

    /** @var string */
    private $streamName;
    /** @var string */
    private $groupName;

    private const BUFFER_COUNT = 10;
    private const EVENT_WRITE_COUNT = self::BUFFER_COUNT * 2;

    /** @var int */
    private $bufferCount = 10;
    /** @var Deferred */
    private $eventsReceived;
    /** @var int */
    private $eventReceivedCount;

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
     * @test
     * @throws Throwable
     */
    public function test(): void
    {
        $this->execute(function () {
            $settings = PersistentSubscriptionSettings::default();

            yield $this->conn->createPersistentSubscriptionAsync(
                $this->streamName,
                $this->groupName,
                $settings,
                DefaultData::adminCredentials()
            );

            yield $this->conn->connectToPersistentSubscriptionAsync(
                $this->streamName,
                $this->groupName,
                new class($this->eventsReceived, $this->eventReceivedCount, self::EVENT_WRITE_COUNT) implements EventAppearedOnAsyncPersistentSubscription {
                    private $eventsReceived;
                    private $eventReceivedCount;
                    private $eventWriteCount;

                    public function __construct(Deferred $eventsReceived, &$eventReceivedCount, $eventWriteCount)
                    {
                        $this->eventsReceived = $eventsReceived;
                        $this->eventReceivedCount = $eventReceivedCount;
                        $this->eventWriteCount = $eventWriteCount;
                    }

                    public function __invoke(
                        AsyncEventStorePersistentSubscription $subscription,
                        ResolvedEvent $resolvedEvent,
                        ?int $retryCount = null
                    ): Promise {
                        $subscription->acknowledge($resolvedEvent);
                        if (++$this->eventReceivedCount === $this->eventWriteCount) {
                            $this->eventsReceived->resolve(true);
                        }

                        return new Success();
                    }
                },
                null,
                10,
                false
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

            try {
                $result = yield timeout($this->eventsReceived->promise(), 10000);
                $this->assertTrue($result);
            } catch (TimeoutException $e) {
                $this->fail('Timed out waiting for events');
            }
        });
    }
}
