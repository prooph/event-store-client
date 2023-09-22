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

namespace ProophTest\EventStoreClient\PersistentSubscriptionManagement;

use Amp\PHPUnit\AsyncTestCase;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventStorePersistentSubscription;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\PersistentSubscriptionNakEventAction;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\Util\Guid;
use Prooph\EventStoreClient\PersistentSubscriptions\PersistentSubscriptionsManager;
use ProophTest\EventStoreClient\CountdownEvent;
use ProophTest\EventStoreClient\DefaultData;
use ProophTest\EventStoreClient\SpecificationWithConnection;

class persistent_subscription_manager extends AsyncTestCase
{
    use SpecificationWithConnection;

    private PersistentSubscriptionsManager $manager;

    private string $stream;

    private PersistentSubscriptionSettings $settings;

    private EventStorePersistentSubscription $sub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new PersistentSubscriptionsManager(
            new EndPoint(
                (string) \getenv('ES_HOST'),
                (int) \getenv('ES_HTTP_PORT')
            ),
            5000,
            false,
            false,
            DefaultData::adminCredentials()
        );
        $this->stream = Guid::generateAsHex();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromCurrent()
            ->build();
    }

    protected function when(): void
    {
        $this->connection->createPersistentSubscription(
            $this->stream,
            'existing',
            $this->settings,
            DefaultData::adminCredentials()
        );

        $this->sub = $this->connection->connectToPersistentSubscription(
            $this->stream,
            'existing',
            function (
                EventStorePersistentSubscription $subscription,
                ResolvedEvent $resolvedEvent,
                ?int $retryCount = null
            ): void {
            },
            null,
            10,
            true,
            DefaultData::adminCredentials()
        );

        $this->connection->appendToStream(
            $this->stream,
            ExpectedVersion::Any,
            [
                new EventData(null, 'whatever', true, \json_encode(['foo' => 2])),
                new EventData(null, 'whatever', true, \json_encode(['bar' => 3])),
            ]
        );
    }

    /** @test */
    public function can_describe_persistent_subscription(): void
    {
        $this->execute(function (): void {
            $details = $this->manager->describe($this->stream, 'existing');

            $this->assertSame($this->stream, $details->eventStreamId());
            $this->assertSame('existing', $details->groupName());
            $this->assertSame(2, $details->totalItemsProcessed());
            $this->assertSame('Live', $details->status());
            $this->assertSame(1, $details->lastKnownEventNumber());
        });
    }

    /** @test */
    public function cannot_describe_persistent_subscription_with_empty_stream_name(): void
    {
        $this->execute(function (): void {
            $this->expectException(InvalidArgumentException::class);
            $this->manager->describe('', 'existing');
        });
    }

    /** @test */
    public function cannot_describe_persistent_subscription_with_empty_group_name(): void
    {
        $this->execute(function (): void {
            $this->expectException(InvalidArgumentException::class);
            $this->manager->describe($this->stream, '');
        });
    }

    /** @test */
    public function can_list_all_persistent_subscriptions(): void
    {
        $this->execute(function (): void {
            $list = $this->manager->list();

            $found = false;
            foreach ($list as $details) {
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

    /** @test */
    public function can_list_all_persistent_subscriptions_using_empty_string(): void
    {
        $this->execute(function (): void {
            $list = $this->manager->list('');

            $found = false;
            foreach ($list as $details) {
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

    /** @test */
    public function can_list_persistent_subscriptions_for_stream(): void
    {
        $this->execute(function (): void {
            $list = $this->manager->list($this->stream);

            $found = false;
            foreach ($list as $details) {
                $this->assertSame($this->stream, $details->eventStreamId());

                if ($details->groupName() === 'existing') {
                    $found = true;

                    break;
                }
            }

            $this->assertTrue($found);
        });
    }

    /** @test */
    public function can_replay_parked_messages(): void
    {
        $this->setTimeout(10);
        $this->execute(function (): void {
            $this->sub->stop();

            $this->sub = $this->connection->connectToPersistentSubscription(
                $this->stream,
                'existing',
                function (
                    EventStorePersistentSubscription $subscription,
                    ResolvedEvent $resolvedEvent,
                    ?int $retryCount = null
                ): void {
                    $subscription->fail(
                        $resolvedEvent,
                        PersistentSubscriptionNakEventAction::Park,
                        'testing'
                    );
                },
                null,
                10,
                false,
                DefaultData::adminCredentials()
            );

            $this->connection->appendToStream(
                $this->stream,
                ExpectedVersion::Any,
                [
                    new EventData(null, 'whatever', true, \json_encode(['foo' => 2])),
                    new EventData(null, 'whatever', true, \json_encode(['bar' => 3])),
                ]
            );

            $this->sub->stop(1);

            $this->manager->replayParkedMessages($this->stream, 'existing', DefaultData::adminCredentials());

            $event = new CountdownEvent(2);

            $this->connection->connectToPersistentSubscription(
                $this->stream,
                'existing',
                function (
                    EventStorePersistentSubscription $subscription,
                    ResolvedEvent $resolvedEvent,
                    ?int $retryCount = null
                ) use ($event): void {
                    $subscription->fail(
                        $resolvedEvent,
                        PersistentSubscriptionNakEventAction::Park,
                        'testing'
                    );

                    $event->signal();
                },
                null,
                10,
                false,
                DefaultData::adminCredentials()
            );

            $this->assertTrue($event->wait(5));
        });
    }

    /** @test */
    public function cannot_replay_parked_with_empty_stream_name(): void
    {
        $this->execute(function (): void {
            $this->expectException(InvalidArgumentException::class);
            $this->manager->replayParkedMessages('', 'existing');
        });
    }

    /** @test */
    public function cannot_replay_parked_with_empty_group_name(): void
    {
        $this->execute(function (): void {
            $this->expectException(InvalidArgumentException::class);
            $this->manager->replayParkedMessages($this->stream, '');
        });
    }
}
