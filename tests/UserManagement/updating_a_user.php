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

namespace ProophTest\EventStoreClient\UserManagement;

use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Exception\UserCommandFailed;
use Prooph\EventStore\Util\Guid;
use ProophTest\EventStoreClient\DefaultData;

class updating_a_user extends TestWithNode
{
    /** @test */
    public function updating_a_user_with_empty_username_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager->updateUser('', 'sascha', ['foo', 'bar'], DefaultData::adminCredentials());
    }

    /** @test */
    public function updating_a_user_with_empty_name_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager->updateUser('sascha', '', ['foo', 'bar'], DefaultData::adminCredentials());
    }

    /** @test */
    public function updating_non_existing_user_throws(): void
    {
        $this->expectException(UserCommandFailed::class);

        $this->manager->updateUser(Guid::generateString(), 'bar', ['foo'], DefaultData::adminCredentials());
    }

    /** @test */
    public function updating_a_user_with_parameters_can_be_read(): void
    {
        $name = Guid::generateString();

        $this->manager->createUser($name, 'ourofull', ['foo', 'bar'], 'password', DefaultData::adminCredentials());

        $this->manager->updateUser($name, 'something', ['bar', 'baz'], DefaultData::adminCredentials());

        $user = $this->manager->getUser($name, DefaultData::adminCredentials());

        $this->assertSame($name, $user->loginName());
        $this->assertSame('something', $user->fullName());
        $this->assertSame(['bar', 'baz'], $user->groups());
    }
}
