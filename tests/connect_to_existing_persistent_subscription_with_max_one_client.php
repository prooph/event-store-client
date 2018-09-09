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

use Amp\Delayed;
use Amp\Promise;
use Amp\Success;
use Error;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\EventAppearedOnPersistentSubscription;
use Prooph\EventStoreClient\Exception\MaximumSubscribersReachedException;
use Prooph\EventStoreClient\Internal\AbstractEventStorePersistentSubscription;
use Prooph\EventStoreClient\Internal\ResolvedEvent;
use Prooph\EventStoreClient\Internal\UuidGenerator;
use Prooph\EventStoreClient\PersistentSubscriptionSettings;
use Throwable;

class connect_to_existing_persistent_subscription_with_max_one_client extends TestCase
{
    use SpecificationWithConnection;

    /** @var string */
    private $stream;
    /** @var PersistentSubscriptionSettings */
    private $settings;
    /** @var string */
    private $group = 'startinbeginning1';

    protected function setUp(): void
    {
        $this->stream = '$' . UuidGenerator::generate();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromCurrent()
            ->withMaxSubscriberCountOf(1)
            ->build();
    }

    protected function given(): Generator
    {
        yield $this->conn->createPersistentSubscriptionAsync(
            $this->stream,
            $this->group,
            $this->settings,
            DefaultData::adminCredentials()
        );

        $this->conn->connectToPersistentSubscription(
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
        $this->conn->connectToPersistentSubscription(
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

        yield new Delayed(50); // wait for it
    }

    /**
     * @test
     * @throws Throwable
     */
    public function the_second_subscription_fails_to_connect(): void
    {
        try {
            $this->executeCallback(function (): Generator {
                yield new Success();
            });

            $this->fail('Should have thrown');
        } catch (Error $exception) {
            $this->assertInstanceOf(MaximumSubscribersReachedException::class, $exception->getPrevious());
        }
    }
}
