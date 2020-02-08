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

use Amp\Promise;
use Amp\Success;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Async\EventAppearedOnPersistentSubscription;
use Prooph\EventStore\Async\EventStorePersistentSubscription;
use Prooph\EventStore\Exception\MaximumSubscribersReached;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\Util\Guid;
use Throwable;

class connect_to_existing_persistent_subscription_with_max_one_client extends TestCase
{
    use SpecificationWithConnection;

    private string $stream;
    private PersistentSubscriptionSettings $settings;
    private Throwable $exception;
    private string $group = 'startinbeginning1';
    private ?EventStorePersistentSubscription $firstSubscription;

    protected function setUp(): void
    {
        $this->stream = '$' . Guid::generateAsHex();
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

        $this->firstSubscription = yield $this->conn->connectToPersistentSubscriptionAsync(
            $this->stream,
            $this->group,
            new class() implements EventAppearedOnPersistentSubscription {
                public function __invoke(
                    EventStorePersistentSubscription $subscription,
                    ResolvedEvent $resolvedEvent,
                    ?int $retryCount = null
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
                        EventStorePersistentSubscription $subscription,
                        ResolvedEvent $resolvedEvent,
                        ?int $retryCount = null
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
        $this->execute(function (): Generator {
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
        $this->execute(function (): Generator {
            $this->assertInstanceOf(MaximumSubscribersReached::class, $this->exception);
            yield new Success();
        });
    }
}
