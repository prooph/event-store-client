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

namespace ProophTest\EventStoreClient\UserManagement;

use Prooph\EventStore\EndPoint;
use Prooph\EventStore\UserManagement\UserDetails;
use Prooph\EventStoreClient\UserManagement\UsersManager;
use ProophTest\EventStoreClient\DefaultData;

class list_users extends TestWithNode
{
    /** @test */
    public function list_all_users_works(): void
    {
        $this->manager->createUser('ouro', 'ourofull', ['foo', 'bar'], 'ouro', DefaultData::adminCredentials());

        $users = $this->manager->listAll(DefaultData::adminCredentials());
        /** @var UserDetails[] $users */

        $this->assertGreaterThanOrEqual(3, \count($users));

        $foundAdmin = false;
        $foundOps = false;
        $foundOuro = false;

        foreach ($users as $user) {
            if ($user->loginName() === 'admin') {
                $foundAdmin = true;
            }

            if ($user->loginName() === 'ops') {
                $foundOps = true;
            }

            if ($user->loginName() === 'ouro') {
                $foundOuro = true;
            }
        }

        $this->assertTrue($foundAdmin);
        $this->assertTrue($foundOps);
        $this->assertTrue($foundOuro);
    }

    /** @test */
    public function list_all_users_falls_back_to_default_credentials(): void
    {
        $manager = new UsersManager(
            new EndPoint(
                (string) \getenv('ES_HOST'),
                (int) \getenv('ES_HTTP_PORT')
            ),
            5,
            false,
            false,
            DefaultData::adminCredentials()
        );

        $manager->createUser('ouro2', 'ourofull', ['foo', 'bar'], 'ouro', DefaultData::adminCredentials());

        /** @var UserDetails[] $users */
        $users = $manager->listAll();

        $this->assertGreaterThanOrEqual(3, $users);

        $foundAdmin = false;
        $foundOps = false;
        $foundOuro = false;

        foreach ($users as $user) {
            if ($user->loginName() === 'admin') {
                $foundAdmin = true;
            }

            if ($user->loginName() === 'ops') {
                $foundOps = true;
            }

            if ($user->loginName() === 'ouro2') {
                $foundOuro = true;
            }
        }

        $this->assertTrue($foundAdmin);
        $this->assertTrue($foundOps);
        $this->assertTrue($foundOuro);
    }
}
