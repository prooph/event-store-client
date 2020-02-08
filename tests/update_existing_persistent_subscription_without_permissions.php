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
use Prooph\EventStore\EventData;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\Util\Guid;
use Throwable;

class update_existing_persistent_subscription_without_permissions extends TestCase
{
    use SpecificationWithConnection;

    private string $stream;
    private PersistentSubscriptionSettings $settings;

    protected function given(): Generator
    {
        $this->stream = Guid::generateAsHex();
        $this->settings = PersistentSubscriptionSettings::create()
            ->doNotResolveLinkTos()
            ->startFromCurrent()
            ->build();

        yield $this->conn->appendToStreamAsync(
            $this->stream,
            ExpectedVersion::ANY,
            [new EventData(null, 'whatever', true, '{"foo":2}')]
        );

        yield $this->conn->createPersistentSubscriptionAsync(
            $this->stream,
            'existing',
            $this->settings,
            DefaultData::adminCredentials()
        );
    }

    /**
     * @test
     * @throws Throwable
     */
    public function the_completion_fails_with_access_denied(): void
    {
        $this->execute(function () {
            $this->expectException(AccessDenied::class);

            yield $this->conn->updatePersistentSubscriptionAsync(
                $this->stream,
                'existing',
                $this->settings
            );
        });
    }
}
