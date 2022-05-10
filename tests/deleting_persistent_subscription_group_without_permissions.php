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
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Util\Guid;

class deleting_persistent_subscription_group_without_permissions extends AsyncTestCase
{
    use SpecificationWithConnection;

    private string $stream;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stream = Guid::generateAsHex();
    }

    /** @test */
    public function the_delete_fails_with_access_denied(): void
    {
        $this->execute(function (): void {
            $this->expectException(AccessDenied::class);

            $this->connection->deletePersistentSubscription(
                $this->stream,
                Guid::generateAsHex()
            );
        });
    }
}
