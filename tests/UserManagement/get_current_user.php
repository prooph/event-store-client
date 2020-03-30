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

namespace ProophTest\EventStoreClient\UserManagement;

use Generator;
use Prooph\EventStore\UserManagement\UserDetails;
use ProophTest\EventStoreClient\DefaultData;

class get_current_user extends TestWithNode
{
    /**
     * @test
     */
    public function returns_the_current_user(): Generator
    {
        yield $this->execute(function (): Generator {
            $user = yield $this->manager->getCurrentUserAsync(DefaultData::adminCredentials());
            \assert($user instanceof UserDetails);

            $this->assertSame('admin', $user->loginName());
            $this->assertSame('Event Store Administrator', $user->fullName());
        });
    }
}
