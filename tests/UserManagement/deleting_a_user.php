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
use Prooph\EventStore\Transport\Http\HttpStatusCode;
use Prooph\EventStore\UserManagement\UserDetails;
use Prooph\EventStore\Util\Guid;
use ProophTest\EventStoreClient\DefaultData;

class deleting_a_user extends TestWithNode
{
    /** @test */
    public function deleting_non_existing_user_throws(): void
    {
        $this->expectException(UserCommandFailed::class);

        try {
            $this->manager->deleteUser(Guid::generateString(), DefaultData::adminCredentials());
        } catch (UserCommandFailed $e) {
            $this->assertSame(HttpStatusCode::NotFound, $e->httpStatusCode());

            throw $e;
        }
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function deleting_created_user_deletes_it(): void
    {
        $user = Guid::generateString();

        $this->manager->createUser($user, 'ourofull', ['foo', 'bar'], 'ouro', DefaultData::adminCredentials());
        $this->manager->deleteUser($user, DefaultData::adminCredentials());
    }

    /** @test */
    public function deleting_empty_user_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager->deleteUser('', DefaultData::adminCredentials());
    }

    /** @test */
    public function can_delete_a_user(): void
    {
        $name = Guid::generateString();

        $this->manager->createUser(
            $name,
            'ouro',
            ['foo', 'bar'],
            'ouro',
            DefaultData::adminCredentials()
        );

        $x = $this->manager->getUser($name, DefaultData::adminCredentials());

        $this->assertInstanceOf(UserDetails::class, $x);

        $this->manager->deleteUser($name, DefaultData::adminCredentials());

        $this->expectException(UserCommandFailed::class);

        $this->manager->getUser($name, DefaultData::adminCredentials());
    }
}
