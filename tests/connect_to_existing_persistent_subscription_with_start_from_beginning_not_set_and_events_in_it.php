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

use function Amp\call;
use Amp\Deferred;
use Amp\Promise;
use Amp\Success;
use Amp\TimeoutException;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Async\EventAppearedOnPersistentSubscription;
use Prooph\EventStore\Async\EventStorePersistentSubscription;
use Prooph\EventStore\EventData;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\Util\Guid;
use Throwable;

class connect_to_existing_persistent_subscription_with_start_from_beginning_not_set_and_events_in_it extends TestCase
{
    use SpecificationWithConnection;

    private string $stream;
    private PersistentSubscriptionSettings $settings;
    private string $group = 'startinbeginning1';
    private Deferred $resetEvent;

    protected function setUp(): void
    {
        $this->stream = '$' . Guid::generateAsHex();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromCurrent()
            ->build();
        $this->resetEvent = new Deferred();
    }

    protected function given(): Generator
    {
        yield $this->writeEvents();

        yield $this->conn->createPersistentSubscriptionAsync(
            $this->stream,
            $this->group,
            $this->settings,
            DefaultData::adminCredentials()
        );

        $deferred = $this->resetEvent;

        yield $this->conn->connectToPersistentSubscriptionAsync(
            $this->stream,
            $this->group,
            new class($deferred) implements EventAppearedOnPersistentSubscription {
                private $deferred;

                public function __construct($deferred)
                {
                    $this->deferred = $deferred;
                }

                public function __invoke(
                    EventStorePersistentSubscription $subscription,
                    ResolvedEvent $resolvedEvent,
                    ?int $retryCount = null
                ): Promise {
                    if ($resolvedEvent->originalEventNumber() === 0) {
                        $this->deferred->resolve(true);
                    }

                    return new Success();
                }
            },
            null,
            10,
            true,
            DefaultData::adminCredentials()
        );
    }

    private function writeEvents(): Promise
    {
        return call(function (): Generator {
            for ($i = 0; $i < 10; $i++) {
                yield $this->conn->appendToStreamAsync(
                    $this->stream,
                    ExpectedVersion::ANY,
                    [new EventData(null, 'test', true, '{"foo":"bar"}')],
                    DefaultData::adminCredentials()
                );
            }
        });
    }

    protected function when(): Generator
    {
        yield $this->writeEvents();
    }

    /**
     * @test
     * @throws Throwable
     */
    public function the_subscription_gets_no_events(): void
    {
        $this->execute(function (): Generator {
            $this->expectException(TimeoutException::class);
            yield Promise\timeout($this->resetEvent->promise(), 1000);
        });
    }
}
