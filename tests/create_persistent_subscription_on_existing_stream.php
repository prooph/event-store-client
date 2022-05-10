<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2022 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2022 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\PHPUnit\AsyncTestCase;
use Generator;
use Prooph\EventStore\EventData;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\Util\Guid;

class create_persistent_subscription_on_existing_stream extends AsyncTestCase
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

    protected function when(): Generator
    {
        yield $this->connection->appendToStreamAsync(
            $this->stream,
            ExpectedVersion::ANY,
            [new EventData(null, 'whatever', true, '{"foo":"bar"}')]
        );
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function the_completion_succeeds(): Generator
    {
        yield $this->execute(function (): Generator {
            yield $this->connection->createPersistentSubscriptionAsync(
                $this->stream,
                'existing',
                $this->settings,
                DefaultData::adminCredentials()
            );
        });
    }
}
