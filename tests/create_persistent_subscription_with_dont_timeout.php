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

use Amp\Success;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\Internal\UuidGenerator;
use Prooph\EventStoreClient\PersistentSubscriptionSettings;
use Throwable;

class create_persistent_subscription_with_dont_timeout extends TestCase
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
            ->dontTimeoutMessages()
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
    public function the_message_timeout_should_be_zero(): void
    {
        $this->assertSame(0, $this->settings->messageTimeoutMilliseconds());
    }

    /**
     * @test
     * @doesNotPerformAssertions
     * @throws Throwable
     */
    public function the_subscription_is_created_without_error(): void
    {
        $this->executeCallback(function () {
            yield $this->conn->createPersistentSubscriptionAsync(
                $this->stream,
                'dont-timeout',
                $this->settings,
                DefaultData::adminCredentials()
            );
        });
    }
}
