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

use Prooph\EventStore\Util\Guid;
use ProophTest\EventStoreClient\DefaultData;

abstract class TestWithUser extends TestWithNode
{
    protected string $username;

    protected function setUp(): void
    {
        parent::setUp();

        $this->username = Guid::generateString();

        $this->manager->createUser(
            $this->username,
            'name',
            ['foo', 'admins'],
            'password',
            DefaultData::adminCredentials()
        );
    }
}
