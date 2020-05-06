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
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use Generator;
use Prooph\EventStore\Async\EventStorePersistentSubscription;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\Util\Guid;

class connect_to_existing_persistent_subscription_with_start_from_two_and_no_stream extends AsyncTestCase
{
    use SpecificationWithConnection;

    private string $stream;
    private PersistentSubscriptionSettings $settings;
    private string $group = 'startinbeginning1';
    private ?ResolvedEvent $firstEvent;
    private Deferred $resetEvent;
    private EventId $eventId;
    private bool $set = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventId = EventId::generate();
        $this->stream = '$' . Guid::generateAsHex();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFrom(2)
            ->build();
        $this->resetEvent = new Deferred();
    }

    protected function given(): Generator
    {
        yield $this->connection->createPersistentSubscriptionAsync(
            $this->stream,
            $this->group,
            $this->settings,
            DefaultData::adminCredentials()
        );

        yield $this->connection->connectToPersistentSubscriptionAsync(
            $this->stream,
            $this->group,
            function (
                EventStorePersistentSubscription $subscription,
                ResolvedEvent $resolvedEvent,
                ?int $retryCount = null
            ): Promise {
                if (! $this->set) {
                    $this->set = true;
                    $this->firstEvent = $resolvedEvent;
                    $this->resetEvent->resolve(true);
                }

                return new Success();
            },
            null,
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
            [new EventData(null, 'test', true, '{"foo":"bar"}')],
            DefaultData::adminCredentials()
        );

        yield $this->connection->appendToStreamAsync(
            $this->stream,
            ExpectedVersion::ANY,
            [new EventData(null, 'test', true, '{"foo":"bar"}')],
            DefaultData::adminCredentials()
        );

        yield $this->connection->appendToStreamAsync(
            $this->stream,
            ExpectedVersion::ANY,
            [new EventData($this->eventId, 'test', true, '{"foo":"bar"}')],
            DefaultData::adminCredentials()
        );
    }

    /** @test */
    public function the_subscription_gets_event_two_as_its_first_event(): Generator
    {
        yield $this->execute(function (): Generator {
            $value = yield Promise\timeout($this->resetEvent->promise(), 10000);
            $this->assertTrue($value);
            $this->assertSame(2, $this->firstEvent->originalEventNumber());
            $this->assertTrue($this->firstEvent->originalEvent()->eventId()->equals($this->eventId));

            yield new Success();
        });
    }
}
