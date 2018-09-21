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

use Amp\Deferred;
use Amp\Promise;
use Amp\Success;
use Amp\TimeoutException;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\EventAppearedOnPersistentSubscription;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\Internal\AbstractEventStorePersistentSubscription;
use Prooph\EventStoreClient\Internal\ResolvedEvent;
use Prooph\EventStoreClient\Internal\UuidGenerator;
use Prooph\EventStoreClient\PersistentSubscriptionSettings;
use Throwable;
use function Amp\call;

class connect_to_existing_persistent_subscription_with_start_from_beginning_not_set_and_events_in_it_async extends TestCase
{
    use SpecificationWithConnection;

    /** @var string */
    private $stream;
    /** @var PersistentSubscriptionSettings */
    private $settings;
    /** @var string */
    private $group = 'startinbeginning1';
    /** @var Deferred */
    private $resetEvent;

    protected function setUp(): void
    {
        $this->stream = '$' . UuidGenerator::generate();
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
                    AbstractEventStorePersistentSubscription $subscription,
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
        $this->executeCallback(function (): Generator {
            $this->expectException(TimeoutException::class);
            yield Promise\timeout($this->resetEvent->promise(), 1000);
        });
    }
}
