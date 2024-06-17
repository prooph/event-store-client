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
use Prooph\EventStore\Exception\InvalidOperationException;
use Prooph\EventStore\Util\Guid;

class deleting_persistent_subscription_group_that_doesnt_exist extends AsyncTestCase
{
    use SpecificationWithConnection;

    private string $stream;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stream = Guid::generateAsHex();
    }

    /** @test */
    public function the_delete_fails_with_argument_exception(): void
    {
        $this->execute(function (): void {
            $this->expectException(InvalidOperationException::class);

            $this->connection->deletePersistentSubscription(
                $this->stream,
                Guid::generateAsHex(),
                DefaultData::adminCredentials()
            );
        });
    }
}
