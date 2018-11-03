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

use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\Exception\AccessDeniedException;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\Internal\UuidGenerator;
use Prooph\EventStoreClient\PersistentSubscriptionSettings;
use Throwable;

class update_existing_persistent_subscription_without_permissions extends TestCase
{
    use SpecificationWithConnection;

    /** @var string */
    private $stream;
    /** @var PersistentSubscriptionSettings */
    private $settings;

    protected function given(): Generator
    {
        $this->stream = UuidGenerator::generate();
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
            $this->expectException(AccessDeniedException::class);

            yield $this->conn->updatePersistentSubscriptionAsync(
                $this->stream,
                'existing',
                $this->settings
            );
        });
    }
}
