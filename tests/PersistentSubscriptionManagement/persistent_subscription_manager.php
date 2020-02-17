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

namespace ProophTest\EventStoreClient\PersistentSubscriptionManagement;

use Amp\Delayed;
use Amp\Promise;
use Amp\Success;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Async\EventAppearedOnPersistentSubscription;
use Prooph\EventStore\Async\EventStorePersistentSubscription;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\EventData;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\PersistentSubscriptionNakEventAction;
use Prooph\EventStore\PersistentSubscriptions\PersistentSubscriptionDetails;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\Transport\Http\EndpointExtensions;
use Prooph\EventStore\Util\Guid;
use Prooph\EventStoreClient\PersistentSubscriptions\PersistentSubscriptionsManager;
use ProophTest\EventStoreClient\CountdownEvent;
use ProophTest\EventStoreClient\DefaultData;
use ProophTest\EventStoreClient\SpecificationWithConnection;
use Throwable;

class persistent_subscription_manager extends TestCase
{
    use SpecificationWithConnection;

    private PersistentSubscriptionsManager $manager;
    private string $stream;
    private PersistentSubscriptionSettings $settings;
    private EventStorePersistentSubscription $sub;

    protected function setUp(): void
    {
        $this->manager = new PersistentSubscriptionsManager(
            new EndPoint(
                (string) \getenv('ES_HOST'),
                (int) \getenv('ES_HTTP_PORT')
            ),
            5000,
            EndpointExtensions::HTTP_SCHEMA,
            DefaultData::adminCredentials()
        );
        $this->stream = Guid::generateAsHex();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromCurrent()
            ->build();
    }

    protected function when(): Generator
    {
        yield $this->conn->createPersistentSubscriptionAsync(
            $this->stream,
            'existing',
            $this->settings,
            DefaultData::adminCredentials()
        );

        $this->sub = yield $this->conn->connectToPersistentSubscriptionAsync(
            $this->stream,
            'existing',
            new class() implements EventAppearedOnPersistentSubscription {
                public function __invoke(
                    EventStorePersistentSubscription $subscription,
                    ResolvedEvent $resolvedEvent,
                    ?int $retryCount = null
                ): Promise {
                    return new Success();
                }
            },
            null,
            10,
            true,
            DefaultData::adminCredentials()
        );

        yield $this->conn->appendToStreamAsync(
            $this->stream,
            ExpectedVersion::ANY,
            [
                new EventData(null, 'whatever', true, \json_encode(['foo' => 2])),
                new EventData(null, 'whatever', true, \json_encode(['bar' => 3])),
            ]
        );
    }

    /**
     * @test
     * @throws Throwable
     */
    public function can_describe_persistent_subscription(): void
    {
        $this->execute(function () {
            $details = yield $this->manager->describe($this->stream, 'existing');
            \assert($details instanceof PersistentSubscriptionDetails);

            $this->assertEquals($this->stream, $details->eventStreamId());
            $this->assertEquals('existing', $details->groupName());
            $this->assertEquals(2, $details->totalItemsProcessed());
            $this->assertEquals('Live', $details->status());
            $this->assertEquals(1, $details->lastKnownEventNumber());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function cannot_describe_persistent_subscription_with_empty_stream_name(): void
    {
        $this->execute(function () {
            $this->expectException(InvalidArgumentException::class);
            yield $this->manager->describe('', 'existing');
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function cannot_describe_persistent_subscription_with_empty_group_name(): void
    {
        $this->execute(function () {
            $this->expectException(InvalidArgumentException::class);
            yield $this->manager->describe($this->stream, '');
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function can_list_all_persistent_subscriptions(): void
    {
        $this->execute(function () {
            $list = yield $this->manager->list();

            $found = false;
            foreach ($list as $details) {
                \assert($details instanceof PersistentSubscriptionDetails);
                if ($details->eventStreamId() === $this->stream
                    && $details->groupName() === 'existing'
                ) {
                    $found = true;
                    break;
                }
            }

            $this->assertTrue($found);
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function can_list_all_persistent_subscriptions_using_empty_string(): void
    {
        $this->execute(function () {
            $list = yield $this->manager->list('');

            $found = false;
            foreach ($list as $details) {
                \assert($details instanceof PersistentSubscriptionDetails);
                if ($details->eventStreamId() === $this->stream
                    && $details->groupName() === 'existing'
                ) {
                    $found = true;
                    break;
                }
            }

            $this->assertTrue($found);
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function can_list_persistent_subscriptions_for_stream(): void
    {
        $this->execute(function () {
            $list = yield $this->manager->list($this->stream);

            $found = false;
            foreach ($list as $details) {
                \assert($details instanceof PersistentSubscriptionDetails);

                $this->assertEquals($this->stream, $details->eventStreamId());

                if ($details->groupName() === 'existing') {
                    $found = true;
                    break;
                }
            }

            $this->assertTrue($found);
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function can_replay_parked_messages(): void
    {
        $this->execute(function () {
            yield $this->sub->stop();

            $this->sub = yield $this->conn->connectToPersistentSubscriptionAsync(
                $this->stream,
                'existing',
                new class() implements EventAppearedOnPersistentSubscription {
                    public function __invoke(
                        EventStorePersistentSubscription $subscription,
                        ResolvedEvent $resolvedEvent,
                        ?int $retryCount = null
                    ): Promise {
                        $subscription->fail(
                            $resolvedEvent,
                            PersistentSubscriptionNakEventAction::park(),
                            'testing'
                        );

                        return new Success();
                    }
                },
                null,
                10,
                false,
                DefaultData::adminCredentials()
            );

            yield $this->conn->appendToStreamAsync(
                $this->stream,
                ExpectedVersion::ANY,
                [
                    new EventData(null, 'whatever', true, \json_encode(['foo' => 2])),
                    new EventData(null, 'whatever', true, \json_encode(['bar' => 3])),
                ]
            );

            $this->sub->stop();

            yield new Delayed(1000); // wait for subscription to drop

            yield $this->manager->replayParkedMessages($this->stream, 'existing', DefaultData::adminCredentials());

            $event = new CountdownEvent(2);

            yield $this->conn->connectToPersistentSubscriptionAsync(
                $this->stream,
                'existing',
                new class($event) implements EventAppearedOnPersistentSubscription {
                    private CountdownEvent $event;

                    public function __construct(CountdownEvent $event)
                    {
                        $this->event = $event;
                    }

                    public function __invoke(
                        EventStorePersistentSubscription $subscription,
                        ResolvedEvent $resolvedEvent,
                        ?int $retryCount = null
                    ): Promise {
                        $subscription->fail(
                            $resolvedEvent,
                            PersistentSubscriptionNakEventAction::park(),
                            'testing'
                        );

                        $this->event->signal();

                        return new Success();
                    }
                },
                null,
                10,
                false,
                DefaultData::adminCredentials()
            );

            $this->assertTrue(yield $event->wait(5000));
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function cannot_replay_parked_with_empty_stream_name(): void
    {
        $this->execute(function () {
            $this->expectException(InvalidArgumentException::class);
            yield $this->manager->replayParkedMessages('', 'existing');
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function cannot_replay_parked_with_empty_group_name(): void
    {
        $this->execute(function () {
            $this->expectException(InvalidArgumentException::class);
            yield $this->manager->replayParkedMessages($this->stream, '');
        });
    }
}
