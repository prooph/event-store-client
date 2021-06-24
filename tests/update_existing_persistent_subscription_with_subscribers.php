<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2021 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2021 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
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
use Generator;
use Prooph\EventStore\Async\EventStorePersistentSubscription;
use Prooph\EventStore\EventData;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SubscriptionDropReason;
use Prooph\EventStore\Util\Guid;
use Throwable;

class update_existing_persistent_subscription_with_subscribers extends AsyncTestCase
{
    use SpecificationWithConnection;

    private string $stream;
    private PersistentSubscriptionSettings $settings;
    private Deferred $dropped;
    private ?SubscriptionDropReason $reason;
    private ?Throwable $exception;
    private ?Throwable $caught = null;

    protected function given(): Generator
    {
        $this->stream = Guid::generateAsHex();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromCurrent()->build();
        $this->dropped = new Deferred();

        yield $this->connection->appendToStreamAsync(
            $this->stream,
            ExpectedVersion::ANY,
            [new EventData(null, 'whatever', true, '{"foo": 2}')]
        );

        yield $this->connection->createPersistentSubscriptionAsync(
            $this->stream,
            'existing',
            $this->settings,
            DefaultData::adminCredentials()
        );

        yield $this->connection->connectToPersistentSubscriptionAsync(
            $this->stream,
            'existing',
            fn (
                EventStorePersistentSubscription $subscription,
                ResolvedEvent $resolvedEvent,
                ?int $retryCount = null
            ): Promise => new Success(),
            function (
                EventStorePersistentSubscription $subscription,
                SubscriptionDropReason $reason,
                ?Throwable $exception = null
            ): void {
                $this->reason = $reason;
                $this->exception = $exception;
                $this->dropped->resolve(true);
            }
        );
    }

    protected function when(): Generator
    {
        try {
            yield $this->connection->updatePersistentSubscriptionAsync(
                $this->stream,
                'existing',
                $this->settings,
                DefaultData::adminCredentials()
            );
        } catch (Throwable $ex) {
            $this->caught = $ex;
        }
    }

    /** @test */
    public function the_completion_succeeds(): Generator
    {
        yield $this->execute(function (): Generator {
            $this->assertNull($this->caught);

            yield new Success();
        });
    }

    /** @test */
    public function existing_subscriptions_are_dropped(): Generator
    {
        yield $this->execute(function (): Generator {
            $this->assertTrue(yield Promise\timeout($this->dropped->promise(), 5000));
            $this->assertInstanceOf(SubscriptionDropReason::class, $this->reason);
            $this->assertTrue($this->reason->equals(SubscriptionDropReason::userInitiated()));
            $this->assertNull($this->exception);
        });
    }
}
