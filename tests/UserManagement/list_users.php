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

namespace ProophTest\EventStoreClient\UserManagement;

use ProophTest\EventStoreClient\DefaultData;

class list_users extends TestWithNode
{
    /** @test */
    public function list_all_users_works(): void
    {
        $this->markTestSkipped('Users are set up and deleted before this test runs so db is in unknown state');

        $this->manager->createUser('ouro', 'ourofull', ['foo', 'bar'], 'ouro', DefaultData::adminCredentials());

        $users = $this->manager->listAll(DefaultData::adminCredentials());

        $this->assertCount(3, $users);

        $this->assertSame('admin', $users[0]->loginName());
        $this->assertSame('Event Store Administrator', $users[0]->fullName());
        $this->assertSame('ops', $users[1]->loginName());
        $this->assertSame('Event Store Operations', $users[1]->fullName());
        $this->assertSame('ouro', $users[2]->loginName());
        $this->assertSame('ourofull', $users[2]->fullName());
    }
}
