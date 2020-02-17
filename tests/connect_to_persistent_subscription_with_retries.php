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

use Amp\Deferred;
use Amp\Promise;
use Amp\Success;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Async\EventAppearedOnPersistentSubscription;
use Prooph\EventStore\Async\EventStorePersistentSubscription;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\PersistentSubscriptionNakEventAction;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\Util\Guid;
use Throwable;

class connect_to_persistent_subscription_with_retries extends TestCase
{
    use SpecificationWithConnection;

    private string $stream;
    private PersistentSubscriptionSettings $settings;
    private Deferred $resetEvent;
    private EventId $eventId;
    private ?int $retryCount;
    private string $group = 'retries';

    protected function setUp(): void
    {
        $this->stream = Guid::generateAsHex();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromBeginning()
            ->build();
        $this->resetEvent = new Deferred();
        $this->eventId = EventId::generate();
    }

    protected function given(): Generator
    {
        yield $this->conn->createPersistentSubscriptionAsync(
            $this->stream,
            'agroupname55',
            $this->settings,
            DefaultData::adminCredentials()
        );

        yield $this->conn->connectToPersistentSubscriptionAsync(
            $this->stream,
            'agroupname55',
            new class($this->retryCount, $this->resetEvent) implements EventAppearedOnPersistentSubscription {
                private ?int $retryCount;
                private $resetEvent;

                public function __construct(&$retryCount, $resetEvent)
                {
                    $this->retryCount = &$retryCount;
                    $this->resetEvent = $resetEvent;
                }

                public function __invoke(
                    EventStorePersistentSubscription $subscription,
                    ResolvedEvent $resolvedEvent,
                    ?int $retryCount = null
                ): Promise {
                    if ($retryCount > 4) {
                        $this->retryCount = $retryCount;
                        $subscription->acknowledge($resolvedEvent);
                        $this->resetEvent->resolve(true);
                    } else {
                        $subscription->fail(
                            $resolvedEvent,
                            PersistentSubscriptionNakEventAction::retry(),
                            'Not yet tried enough times'
                        );
                    }

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
        yield $this->conn->appendToStreamAsync(
            $this->stream,
            ExpectedVersion::ANY,
            [new EventData($this->eventId, 'test', true, '{"foo":"bar"}')],
            DefaultData::adminCredentials()
        );
    }

    /**
     * @test
     * @throws Throwable
     */
    public function events_are_retried_until_success(): void
    {
        $this->execute(function (): Generator {
            $value = yield Promise\timeout($this->resetEvent->promise(), 10000);
            $this->assertTrue($value);
            $this->assertSame(5, $this->retryCount);
        });
    }
}
