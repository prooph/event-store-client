<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2021 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2021 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Success;
use Generator;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\Util\Guid;

class create_persistent_subscription_with_dont_timeout extends AsyncTestCase
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
            ->dontTimeoutMessages()
            ->build();
    }

    protected function when(): Generator
    {
        yield new Success();
    }

    /** @test */
    public function the_message_timeout_should_be_zero(): void
    {
        $this->assertSame(0, $this->settings->messageTimeoutMilliseconds());
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function the_subscription_is_created_without_error(): Generator
    {
        yield $this->execute(function (): Generator {
            yield $this->connection->createPersistentSubscriptionAsync(
                $this->stream,
                'dont-timeout',
                $this->settings,
                DefaultData::adminCredentials()
            );
        });
    }
}
