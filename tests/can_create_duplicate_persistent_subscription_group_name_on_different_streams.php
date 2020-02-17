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

class can_create_duplicate_persistent_subscription_group_name_on_different_streams extends TestCase
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
            'group3211',
            $this->settings,
            DefaultData::adminCredentials()
        );
    }

    /**
     * @test
     * @doesNotPerformAssertions
     * @throws Throwable
     */
    public function the_completion_succeeds(): void
    {
        $this->execute(function () {
            yield $this->conn->createPersistentSubscriptionAsync(
                'someother' . $this->stream,
                'group3211',
                $this->settings,
                DefaultData::adminCredentials()
            );
        });
    }
}
