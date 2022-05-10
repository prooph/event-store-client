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

namespace ProophTest\EventStoreClient;

use Amp\Deferred;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\TimeoutException;
use Exception;
use Generator;
use Prooph\EventStore\Async\EventStorePersistentSubscription;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SubscriptionDropReason;
use Prooph\EventStore\Util\Guid;
use Throwable;

class a_nak_in_subscription_handler_in_autoack_mode_drops_the_subscription extends AsyncTestCase
{
    use SpecificationWithConnection;

    private string $stream;
    private PersistentSubscriptionSettings $settings;
    private Deferred $resetEvent;
    private ?\Throwable $exception;
    private SubscriptionDropReason $reason;
    private string $group;

    protected function setUp(): void
    {
        parent::setUp();

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
        yield $this->connection->createPersistentSubscriptionAsync(
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

        yield $this->connection->connectToPersistentSubscriptionAsync(
            $this->stream,
            $this->group,
            function (
                EventStorePersistentSubscription $subscription,
                ResolvedEvent $resolvedEvent,
                ?int $retryCount = null
            ): Promise {
                throw new \Exception('test');
            },
            function (
                EventStorePersistentSubscription $subscription,
                SubscriptionDropReason $reason,
                ?Throwable $exception = null
            ) use ($dropBehaviour): void {
                ($dropBehaviour)($reason, $exception);
            },
            10,
            true,
            DefaultData::adminCredentials()
        );
    }

    protected function when(): Generator
    {
        yield $this->connection->appendToStreamAsync(
            $this->stream,
            ExpectedVersion::ANY,
            [
                new EventData(EventId::generate(), 'test', true, '{"foo: "bar"}'),
            ],
            DefaultData::adminCredentials()
        );
    }

    /** @test */
    public function the_subscription_gets_dropped(): Generator
    {
        yield $this->execute(function (): Generator {
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
