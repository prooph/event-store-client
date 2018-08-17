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

use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Exception\UserCommandFailedException;
use ProophTest\EventStoreClient\DefaultData;
use Ramsey\Uuid\Uuid;

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
        $this->expectException(UserCommandFailedException::class);

        $this->manager->updateUser(Uuid::uuid4()->toString(), 'bar', ['foo'], DefaultData::adminCredentials());
    }

    /** @test */
    public function updating_a_user_with_parameters_can_be_read(): void
    {
        $name = Uuid::uuid4()->toString();

        $this->manager->createUser($name, 'ourofull', ['foo', 'bar'], 'password', DefaultData::adminCredentials());

        $this->manager->updateUser($name, 'something', ['bar', 'baz'], DefaultData::adminCredentials());

        $user = $this->manager->getUser($name, DefaultData::adminCredentials());

        $this->assertSame($name, $user->loginName());
        $this->assertSame('something', $user->fullName());
        $this->assertEquals(['bar', 'baz'], $user->groups());
    }
}
