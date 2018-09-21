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
use Error;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\EventAppearedOnPersistentSubscription;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Internal\AbstractEventStorePersistentSubscription;
use Prooph\EventStoreClient\Internal\ResolvedEvent;
use Prooph\EventStoreClient\Internal\UuidGenerator;
use Prooph\EventStoreClient\PersistentSubscriptionSettings;
use Throwable;

class connect_to_non_existing_persistent_subscription_with_permissions extends TestCase
{
    use SpecificationWithConnection;

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
        $this->conn->connectToPersistentSubscription(
            'nonexisting2',
            'foo',
            new class() implements EventAppearedOnPersistentSubscription {
                public function __invoke(
                    AbstractEventStorePersistentSubscription $subscription,
                    ResolvedEvent $resolvedEvent,
                    ?int $retryCount = null
                ): Promise {
                    return new Success();
                }
            }
        );

        yield new Delayed(50); // wait for it

        $this->fail('should have thrown');
    }

    /**
     * @test
     * @throws Throwable
     */
    public function the_completion_fails(): void
    {
        try {
            $this->executeCallback(function (): Generator {
                yield new Success();
            });
        } catch (Error $e) {
            $this->assertInstanceOf(InvalidArgumentException::class, $e->getPrevious());
        }
    }
}
