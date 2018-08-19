<?php
/**
 * This file is part of the prooph/event-store-client.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\Promise;
use Amp\Success;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\EventAppearedOnPersistentSubscription;
use Prooph\EventStoreClient\Exception\MaximumSubscribersReachedException;
use Prooph\EventStoreClient\Internal\AbstractEventStorePersistentSubscription;
use Prooph\EventStoreClient\Internal\EventStorePersistentSubscription;
use Prooph\EventStoreClient\Internal\ResolvedEvent;
use Prooph\EventStoreClient\Internal\UuidGenerator;
use Prooph\EventStoreClient\NamedConsumerStrategy;
use Prooph\EventStoreClient\PersistentSubscriptionSettings;
use Throwable;

class connect_to_existing_persistent_subscription_with_max_one_client_async extends TestCase
{
    use SpecificationWithConnection;

    /** @var string */
    private $stream;
    /** @var PersistentSubscriptionSettings */
    private $settings;
    /** @var Throwable */
    private $exception;
    /** @var string */
    private $group = 'startinbeginning1';
    /** @var EventStorePersistentSubscription|null */
    private $firstSubscription;

    protected function setUp(): void
    {
        $this->stream = '$' . UuidGenerator::generate();
        $this->settings = new PersistentSubscriptionSettings(
            false,
            -1,
            false,
            2000,
            500,
            10,
            20,
            1000,
            500,
            1,
            30000,
            10,
            NamedConsumerStrategy::roundRobin()
        );
    }

    protected function given(): Generator
    {
        yield $this->conn->createPersistentSubscriptionAsync(
            $this->stream,
            $this->group,
            $this->settings,
            DefaultData::adminCredentials()
        );

        $this->firstSubscription = yield $this->conn->connectToPersistentSubscriptionAsync(
            $this->stream,
            $this->group,
            new class() implements EventAppearedOnPersistentSubscription {
                public function __invoke(
                    AbstractEventStorePersistentSubscription $subscription,
                    ResolvedEvent $resolvedEvent
                ): Promise {
                    $subscription->acknowledge($resolvedEvent);

                    return new Success();
                }
            },
            null,
            10,
            false,
            DefaultData::adminCredentials()
        );
    }

    protected function when(): Generator
    {
        try {
            yield $this->conn->connectToPersistentSubscriptionAsync(
                $this->stream,
                $this->group,
                new class() implements EventAppearedOnPersistentSubscription {
                    public function __invoke(
                        AbstractEventStorePersistentSubscription $subscription,
                        ResolvedEvent $resolvedEvent
                    ): Promise {
                        $subscription->acknowledge($resolvedEvent);

                        return new Success();
                    }
                },
                null,
                10,
                false,
                DefaultData::adminCredentials()
            );
        } catch (Throwable $e) {
            $this->exception = $e;
        }
    }

    /**
     * @test
     * @throws Throwable
     */
    public function the_first_subscription_connects_successfully(): void
    {
        $this->executeCallback(function (): Generator {
            $this->assertNotNull($this->firstSubscription);

            yield new Success();
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function the_second_subscription_throws_maximum_subscribers_reached_exception(): void
    {
        $this->executeCallback(function (): Generator {
            $this->assertInstanceOf(MaximumSubscribersReachedException::class, $this->exception);
            yield new Success();
        });
    }
}
