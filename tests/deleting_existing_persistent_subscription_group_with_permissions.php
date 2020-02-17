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

use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\Util\Guid;
use Throwable;

class deleting_existing_persistent_subscription_group_with_permissions extends TestCase
{
    use SpecificationWithConnection;

    private string $stream;
    private PersistentSubscriptionSettings $settings;

    protected function setUp(): void
    {
        $this->stream = Guid::generateAsHex();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromCurrent()
            ->build();
    }

    protected function when(): Generator
    {
        yield $this->conn->createPersistentSubscriptionAsync(
            $this->stream,
            'groupname123',
            $this->settings,
            DefaultData::adminCredentials()
        );
    }

    /**
     * @test
     * @doesNotPerformAssertions
     * @throws Throwable
     */
    public function the_delete_of_group_succeeds(): void
    {
        $this->execute(function () {
            yield $this->conn->deletePersistentSubscriptionAsync(
                $this->stream,
                'groupname123',
                DefaultData::adminCredentials()
            );
        });
    }
}
