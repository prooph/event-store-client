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

use Amp\Promise;
use Amp\Success;
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

    /** @var Throwable */
    private $exception;

    protected function when(): Generator
    {
        try {
            yield $this->conn->connectToPersistentSubscriptionAsync(
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

            $this->fail('should have thrown');
        } catch (Throwable $e) {
            $this->exception = $e;
        }
    }

    /**
     * @test
     * @throws Throwable
     */
    public function the_subscription_fails_to_connect_with_invalid_argument_exception(): void
    {
        $this->execute(function (): Generator {
            $this->assertNotNull($this->exception);
            $this->assertInstanceOf(InvalidArgumentException::class, $this->exception);

            yield new Success();
        });
    }
}
