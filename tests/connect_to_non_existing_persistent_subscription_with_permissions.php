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

use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use Generator;
use Prooph\EventStore\Async\EventStorePersistentSubscription;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\ResolvedEvent;
use Throwable;

class connect_to_non_existing_persistent_subscription_with_permissions extends AsyncTestCase
{
    use SpecificationWithConnection;

    private Throwable $exception;

    protected function when(): Generator
    {
        try {
            yield $this->connection->connectToPersistentSubscriptionAsync(
                'nonexisting2',
                'foo',
                fn (
                    EventStorePersistentSubscription $subscription,
                    ResolvedEvent $resolvedEvent,
                    ?int $retryCount = null
                ): Promise => new Success()
            );

            $this->fail('should have thrown');
        } catch (Throwable $e) {
            $this->exception = $e;
        }
    }

    /** @test */
    public function the_subscription_fails_to_connect_with_invalid_argument_exception(): Generator
    {
        yield $this->execute(function (): Generator {
            $this->assertNotNull($this->exception);
            $this->assertInstanceOf(InvalidArgumentException::class, $this->exception);

            yield new Success();
        });
    }
}
