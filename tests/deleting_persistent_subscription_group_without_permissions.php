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

use Amp\Success;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Util\Guid;
use Throwable;

class deleting_persistent_subscription_group_without_permissions extends TestCase
{
    use SpecificationWithConnection;

    private string $stream;

    protected function setUp(): void
    {
        $this->stream = Guid::generateAsHex();
    }

    protected function when(): Generator
    {
        yield new Success();
    }

    /**
     * @test
     * @throws Throwable
     */
    public function the_delete_fails_with_access_denied(): void
    {
        $this->execute(function () {
            $this->expectException(AccessDenied::class);

            yield $this->conn->deletePersistentSubscriptionAsync(
                $this->stream,
                Guid::generateAsHex()
            );
        });
    }
}
