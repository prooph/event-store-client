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
use Amp\TimeoutException;
use Closure;
use Exception;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\EventAppearedOnPersistentSubscription;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\EventId;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\Internal\AbstractEventStorePersistentSubscription;
use Prooph\EventStoreClient\Internal\ResolvedEvent;
use Prooph\EventStoreClient\PersistentSubscriptionDropped;
use Prooph\EventStoreClient\PersistentSubscriptionSettings;
use Prooph\EventStoreClient\SubscriptionDropReason;
use Prooph\EventStoreClient\Util\Guid;
use Throwable;

class a_nak_in_subscription_handler_in_autoack_mode_drops_the_subscription extends TestCase
{
    use SpecificationWithConnection;

    /** @var string */
    private $stream;
    /** @var PersistentSubscriptionSettings */
    private $settings;
    /** @var Deferred */
    private $resetEvent;
    /** @var Throwable */
    private $exception;
    /** @var SubscriptionDropReason */
    private $reason;
    /** @var string */
    private $group;

    protected function setUp()
    {
        $this->stream = '$' . Guid::generateString();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromBeginning()
            ->build();
        $this->resetEvent = new Deferred();
        $this->group = 'naktest';
    }

    protected function given(): Generator
    {
        yield $this->conn->createPersistentSubscriptionAsync(
            $this->stream,
            $this->group,
            $this->settings,
            DefaultData::adminCredentials()
        );

        $dropBehaviour = function (
            SubscriptionDropReason $reason,
            ?Throwable $exception = null
        ): void {
            $this->reason = $reason;
            $this->exception = $exception;
            $this->resetEvent->resolve(true);
        };

        yield $this->conn->connectToPersistentSubscriptionAsync(
            $this->stream,
            $this->group,
            new class() implements EventAppearedOnPersistentSubscription {
                public function __invoke(
                    AbstractEventStorePersistentSubscription $subscription,
                    ResolvedEvent $resolvedEvent,
                    ?int $retryCount = null
                ): Promise {
                    throw new \Exception('test');
                }
            },
            new class(Closure::fromCallable($dropBehaviour)) implements PersistentSubscriptionDropped {
                private $callback;

                public function __construct(callable $callback)
                {
                    $this->callback = $callback;
                }

                public function __invoke(
                    AbstractEventStorePersistentSubscription $subscription,
                    SubscriptionDropReason $reason,
                    ?Throwable $exception = null
                ): void {
                    ($this->callback)($reason, $exception);
                }
            },
            10,
            true,
            DefaultData::adminCredentials()
        );
    }

    protected function when(): Generator
    {
        yield $this->conn->appendToStreamAsync(
            $this->stream,
            ExpectedVersion::ANY,
            [
                new EventData(EventId::generate(), 'test', true, '{"foo: "bar"}'),
            ],
            DefaultData::adminCredentials()
        );
    }

    /**
     * @test
     * @throws Throwable
     */
    public function the_subscription_gets_dropped(): void
    {
        $this->execute(function (): Generator {
            try {
                $result = yield Promise\timeout($this->resetEvent->promise(), 5000);

                $this->assertTrue($result);
                $this->assertTrue($this->reason->equals(SubscriptionDropReason::eventHandlerException()));
                $this->assertInstanceOf(Exception::class, $this->exception);
                $this->assertSame('test', $this->exception->getMessage());
            } catch (TimeoutException $e) {
                $this->fail('Timed out');
            }
        });
    }
}
