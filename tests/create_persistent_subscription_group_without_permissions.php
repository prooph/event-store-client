<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2024 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2024 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\PHPUnit\AsyncTestCase;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\Util\Guid;
use Throwable;

class create_persistent_subscription_group_without_permissions extends AsyncTestCase
{
    use SpecificationWithConnection;

    private string $stream;

    private PersistentSubscriptionSettings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stream = Guid::generateAsHex();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromCurrent()
            ->build();
    }

    /** @test */
    public function the_completion_succeeds(): void
    {
        $this->execute(function (): void {
            try {
                $this->connection->createPersistentSubscription(
                    $this->stream,
                    'nonexistinggroup',
                    $this->settings
                );

                $this->fail('Should have thrown');
            } catch (Throwable $e) {
                $this->assertInstanceOf(AccessDenied::class, $e);
            }
        });
    }
}
