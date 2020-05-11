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

class connect_to_existing_persistent_subscription_with_start_from_beginning_and_no_stream extends AsyncTestCase
{
    use SpecificationWithConnection;

    private string $stream;
    private PersistentSubscriptionSettings $settings;
    private string $group = 'startinbeginning1';
    private ?ResolvedEvent $firstEvent;
    private Deferred $resetEvent;
    private array $ids = [];
    private bool $set = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stream = '$' . Guid::generateAsHex();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromBeginning()
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

    private function writeEvents(): Promise
    {
        return call(function (): Generator {
            for ($i = 0; $i < 10; $i++) {
                $this->ids[$i] = EventId::generate();

                yield $this->connection->appendToStreamAsync(
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

    /** @test */
    public function the_subscription_gets_event_zero_as_its_first_event(): Generator
    {
        yield $this->execute(function (): Generator {
            $value = yield Promise\timeout($this->resetEvent->promise(), 5000);
            $this->assertTrue($value);
            $this->assertSame(0, $this->firstEvent->originalEventNumber());
            $this->assertTrue($this->firstEvent->originalEvent()->eventId()->equals($this->ids[0]));

            yield new Success();
        });
    }
}
