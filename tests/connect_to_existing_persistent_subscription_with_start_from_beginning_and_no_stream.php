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
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\EventAppearedOnPersistentSubscription;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\EventId;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\Internal\AbstractEventStorePersistentSubscription;
use Prooph\EventStoreClient\Internal\ResolvedEvent;
use Prooph\EventStoreClient\Internal\UuidGenerator;
use Prooph\EventStoreClient\PersistentSubscriptionSettings;
use Throwable;
use function Amp\call;

class connect_to_existing_persistent_subscription_with_start_from_beginning_and_no_stream extends TestCase
{
    use SpecificationWithConnection;

    /** @var string */
    private $stream;
    /** @var PersistentSubscriptionSettings */
    private $settings;
    /** @var string */
    private $group = 'startinbeginning1';
    /** @var ResolvedEvent */
    private $firstEvent;
    /** @var Deferred */
    private $resetEvent;
    /** @var array */
    private $ids = [];
    /** @var bool */
    private $set = false;

    protected function setUp(): void
    {
        $this->stream = '$' . UuidGenerator::generate();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromBeginning()
            ->build();
        $this->resetEvent = new Deferred();
    }

    protected function given(): Generator
    {
        yield $this->conn->createPersistentSubscriptionAsync(
            $this->stream,
            $this->group,
            $this->settings,
            DefaultData::adminCredentials()
        );

        $set = &$this->set;
        $deferred = $this->resetEvent;
        $firstEvent = &$this->firstEvent;

        $this->conn->connectToPersistentSubscription(
            $this->stream,
            $this->group,
            new class($set, $deferred, $firstEvent) implements EventAppearedOnPersistentSubscription {
                private $set;
                private $deferred;
                private $firstEvent;

                public function __construct(&$set, &$deferred, &$firstEvent)
                {
                    $this->set = &$set;
                    $this->deferred = $deferred;
                    $this->firstEvent = &$firstEvent;
                }

                public function __invoke(
                    AbstractEventStorePersistentSubscription $subscription,
                    ResolvedEvent $resolvedEvent,
                    ?int $retryCount = null
                ): Promise {
                    if (! $this->set) {
                        $this->set = true;
                        $this->firstEvent = $resolvedEvent;
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
                $this->ids[$i] = EventId::generate();

                yield $this->conn->appendToStreamAsync(
                    $this->stream,
                    ExpectedVersion::ANY,
                    [new EventData($this->ids[$i], 'test', true, '{"foo":"bar"}')],
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
    public function the_subscription_gets_event_zero_as_its_first_event(): void
    {
        $this->executeCallback(function (): Generator {
            $value = yield Promise\timeout($this->resetEvent->promise(), 10000);
            $this->assertTrue($value);
            $this->assertSame(0, $this->firstEvent->originalEventNumber());
            $this->assertTrue($this->firstEvent->originalEvent()->eventId()->equals($this->ids[0]));

            yield new Success();
        });
    }
}
