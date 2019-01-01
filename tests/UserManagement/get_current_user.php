<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2019 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient\UserManagement;

use Prooph\EventStore\UserManagement\UserDetails;
use ProophTest\EventStoreClient\DefaultData;
use Throwable;

class get_current_user extends TestWithNode
{
    /**
     * @test
     * @throws Throwable
     */
    public function returns_the_current_user(): void
    {
        $this->execute(function () {
            $user = yield $this->manager->getCurrentUserAsync(DefaultData::adminCredentials());
            \assert($user instanceof UserDetails);

            $this->assertSame('admin', $user->loginName());
            $this->assertSame('Event Store Administrator', $user->fullName());
        });
    }
}
