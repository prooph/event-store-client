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
use Amp\Success;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\AsyncEventStorePersistentSubscription;
use Prooph\EventStore\EventAppearedOnAsyncPersistentSubscription;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\Util\Guid;
use Throwable;
use function Amp\call;

class connect_to_existing_persistent_subscription_with_start_from_x_set_and_events_in_it extends TestCase
{
    use SpecificationWithConnection;

    /** @var string */
    private $stream;
    /** @var PersistentSubscriptionSettings */
    private $settings;
    /** @var string */
    private $group = 'startinx2';
    /** @var Deferred */
    private $resetEvent;
    /** @var ResolvedEvent */
    private $firstEvent;
    /** @var EventId */
    private $eventId;

    protected function setUp(): void
    {
        $this->stream = '$' . Guid::generateAsHex();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFrom(4)
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

        yield $this->conn->connectToPersistentSubscriptionAsync(
            $this->stream,
            $this->group,
            new class($this->resetEvent, $this->firstEvent) implements EventAppearedOnAsyncPersistentSubscription {
                private $deferred;
                private $firstEvent;
                private $set = false;

                public function __construct($deferred, &$firstEvent)
                {
                    $this->deferred = $deferred;
                    $this->firstEvent = &$firstEvent;
                }

                public function __invoke(
                    AsyncEventStorePersistentSubscription $subscription,
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
                $id = EventId::generate();

                yield $this->conn->appendToStreamAsync(
                    $this->stream,
                    ExpectedVersion::ANY,
                    [new EventData($id, 'test', true, '{"foo":"bar"}')],
                    DefaultData::adminCredentials()
                );

                if ($i === 4) {
                    $this->eventId = $id;
                }
            }
        });
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
    public function the_subscription_gets_the_written_event_as_its_first_event(): void
    {
        $this->execute(function (): Generator {
            $value = yield Promise\timeout($this->resetEvent->promise(), 10000);
            $this->assertTrue($value);
            $this->assertNotNull($this->firstEvent);
            $this->assertSame(4, $this->firstEvent->originalEventNumber());
            $this->assertTrue($this->firstEvent->originalEvent()->eventId()->equals($this->eventId));
        });
    }
}
