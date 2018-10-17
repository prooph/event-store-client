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

namespace ProophTest\EventStoreClient\UserManagement;

use ProophTest\EventStoreClient\DefaultData;

class get_current_user extends TestWithNode
{
    /** @test */
    public function returns_the_current_user(): void
    {
        $user = $this->manager->getCurrentUser(DefaultData::adminCredentials());

        $this->assertSame('admin', $user->loginName());
        $this->assertSame('Event Store Administrator', $user->fullName());
    }
}
