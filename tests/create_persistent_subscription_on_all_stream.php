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

use Amp\Success;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Throwable;

class create_persistent_subscription_on_all_stream extends TestCase
{
    use SpecificationWithConnection;

    /** PersistentSubscriptionSettings */
    private PersistentSubscriptionSettings $settings;

    protected function setUp(): void
    {
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromCurrent()
            ->build();
    }

    protected function when(): Generator
    {
        yield new Success();
    }

    /**
     * @test
     * @throws Throwable
     */
    public function the_completion_fails_with_invalid_stream(): void
    {
        $this->execute(function () {
            try {
                yield $this->conn->createPersistentSubscriptionAsync(
                    '$all',
                    'shitbird',
                    $this->settings
                );

                $this->fail('Should have thrown');
            } catch (Throwable $e) {
                $this->assertInstanceOf(AccessDenied::class, $e);
            }
        });
    }
}
