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
use Prooph\EventStoreClient\Internal\AbstractEventStorePersistentSubscription;
use Prooph\EventStoreClient\Internal\ResolvedEvent;
use Prooph\EventStoreClient\PersistentSubscriptionSettings;
use Prooph\EventStoreClient\Util\UuidGenerator;
use Throwable;

class connect_to_existing_persistent_subscription_with_permissions extends TestCase
{
    use SpecificationWithConnection;

    /** @var Promise */
    private $sub;
    /** @var string */
    private $stream;
    /** @var PersistentSubscriptionSettings */
    private $settings;

    protected function setUp(): void
    {
        $this->stream = UuidGenerator::generate();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromCurrent()
            ->build();
    }

    protected function when(): Generator
    {
        yield $this->conn->createPersistentSubscriptionAsync(
            $this->stream,
            'agroupname17',
            $this->settings,
            DefaultData::adminCredentials()
        );

        $this->sub = $this->conn->connectToPersistentSubscriptionAsync(
            $this->stream,
            'agroupname17',
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
    }

    /**
     * @test
     * @throws Throwable
     */
    public function the_subscription_suceeds(): void
    {
        $this->execute(function () {
            $this->assertNotNull(yield $this->sub);
        });
    }
}
