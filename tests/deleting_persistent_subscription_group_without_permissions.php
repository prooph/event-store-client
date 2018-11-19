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

use Amp\Success;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\Exception\AccessDeniedException;
use Prooph\EventStoreClient\Util\Uuid;
use Throwable;

class deleting_persistent_subscription_group_without_permissions extends TestCase
{
    use SpecificationWithConnection;

    /** @var string */
    private $stream;

    protected function setUp(): void
    {
        $this->stream = Uuid::generateAsHex();
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
            $this->expectException(AccessDeniedException::class);

            yield $this->conn->deletePersistentSubscriptionAsync(
                $this->stream,
                Uuid::generateAsHex()
            );
        });
    }
}
