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

use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\EventData;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\Internal\UuidGenerator;
use Prooph\EventStoreClient\PersistentSubscriptionSettings;

class create_persistent_subscription_after_deleting_the_same extends TestCase
{
    use SpecificationWithConnection;

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
        yield $this->conn->appendToStreamAsync(
            $this->stream,
            ExpectedVersion::ANY,
            [
                new EventData(null, 'whatever', true, \json_encode(['foo' => 2])),
            ]
        );

        yield $this->conn->createPersistentSubscriptionAsync(
            $this->stream,
            'existing',
            $this->settings,
            DefaultData::adminCredentials()
        );

        yield $this->conn->deletePersistentSubscriptionAsync(
            $this->stream,
            'existing',
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
        $this->executeCallback(function () {
            yield $this->conn->createPersistentSubscriptionAsync(
                $this->stream,
                'existing',
                $this->settings,
                DefaultData::adminCredentials()
            );
        });
    }
}
