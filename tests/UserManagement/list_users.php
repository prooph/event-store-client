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

use Prooph\EventStoreClient\EndPoint;
use Prooph\EventStoreClient\Transport\Http\EndpointExtensions;
use Prooph\EventStoreClient\UserManagement\SyncUsersManager;
use ProophTest\EventStoreClient\DefaultData;

class list_users extends TestWithNode
{
    /** @test */
    public function list_all_users_works(): void
    {
        $this->manager->createUser('ouro', 'ourofull', ['foo', 'bar'], 'ouro', DefaultData::adminCredentials());

        $users = $this->manager->listAll(DefaultData::adminCredentials());

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
        $manager = new SyncUsersManager(
            new EndPoint(
                \getenv('ES_HOST'),
                (int) \getenv('ES_HTTP_PORT')
            ),
            5000,
            EndpointExtensions::HTTP_SCHEMA,
            DefaultData::adminCredentials()
        );

        $manager->createUser('ouro2', 'ourofull', ['foo', 'bar'], 'ouro', DefaultData::adminCredentials());

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
