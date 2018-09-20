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

use Amp\Delayed;
use Amp\Promise;
use Amp\Success;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\EventAppearedOnPersistentSubscription;
use Prooph\EventStoreClient\Exception\AccessDeniedException;
use Prooph\EventStoreClient\Internal\AbstractEventStorePersistentSubscription;
use Prooph\EventStoreClient\Internal\ResolvedEvent;
use Prooph\EventStoreClient\Internal\UuidGenerator;
use Prooph\EventStoreClient\PersistentSubscriptionSettings;
use Throwable;

class connect_to_existing_persistent_subscription_without_permissions extends TestCase
{
    use SpecificationWithConnection;

    /** @var string */
    private $stream;
    /** @var PersistentSubscriptionSettings */
    private $settings;

    protected function setUp(): void
    {
        $this->stream = '$' . UuidGenerator::generate();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromCurrent()
            ->build();
    }

    protected function when(): Generator
    {
        yield $this->conn->createPersistentSubscriptionAsync(
            $this->stream,
            'agroupname55',
            $this->settings,
            DefaultData::adminCredentials()
        );
    }

    /**
     * @test
     * @throws Throwable
     */
    public function the_subscription_fails_to_connect(): void
    {
        try {
            $this->executeCallback(function (): Generator {
                $this->conn->connectToPersistentSubscription(
                    $this->stream,
                    'agroupname55',
                    new class() implements EventAppearedOnPersistentSubscription {
                        public function __invoke(
                            AbstractEventStorePersistentSubscription $subscription,
                            ResolvedEvent $resolvedEvent
                        ): Promise {
                            return new Success();
                        }
                    }
                );

                yield new Delayed(50); // wait for it

                $this->fail('Should have thrown');
            });
        } catch (Throwable $e) {
            $this->assertInstanceOf(AccessDeniedException::class, $e->getPrevious());
        }
    }
}
