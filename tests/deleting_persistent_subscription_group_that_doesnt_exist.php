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

    protected function when(): Generator
    {
        yield new Success();
    }

    /** @test */
    public function the_delete_fails_with_argument_exception(): Generator
    {
        yield $this->execute(function (): Generator {
            $this->expectException(InvalidOperationException::class);

            yield $this->connection->deletePersistentSubscriptionAsync(
                $this->stream,
                Guid::generateAsHex(),
                DefaultData::adminCredentials()
            );
        });
    }
}
