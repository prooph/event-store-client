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
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\EventAppearedOnPersistentSubscription;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\Internal\AbstractEventStorePersistentSubscription;
use Prooph\EventStoreClient\Internal\ResolvedEvent;
use Prooph\EventStoreClient\PersistentSubscriptionNakEventAction;
use Prooph\EventStoreClient\PersistentSubscriptionSettings;
use Prooph\EventStoreClient\Util\UuidGenerator;
use Throwable;

class when_writing_and_subscribing_to_normal_events_manual_nack extends TestCase
{
    use SpecificationWithConnection;

    /** @var string */
    private $streamName;
    /** @var string */
    private $groupName;

    public const BUFFER_COUNT = 10;
    public const EVENT_WRITE_COUNT = self::BUFFER_COUNT * 2;

    /** @var Deferred */
    private $eventsReceived;
    /** @var int */
    private $eventReceivedCount = 0;

    protected function setUp(): void
    {
        $this->streamName = UuidGenerator::generateWithoutDash();
        $this->groupName = UuidGenerator::generateWithoutDash();
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
                    /** @var int */
                    private $eventReceivedCount;
                    /** @var Deferred */
                    private $eventsReceived;

                    public function __construct(int &$eventReceivedCount, Deferred $eventsReceived)
                    {
                        $this->eventReceivedCount = &$eventReceivedCount;
                        $this->eventsReceived = $eventsReceived;
                    }

                    public function __invoke(
                        AbstractEventStorePersistentSubscription $subscription,
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
