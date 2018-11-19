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

use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Exception\UserCommandFailedException;
use Prooph\EventStoreClient\Transport\Http\HttpStatusCode;
use Prooph\EventStoreClient\UserManagement\UserDetails;
use Prooph\EventStoreClient\Util\UuidGenerator;
use ProophTest\EventStoreClient\DefaultData;

class deleting_a_user extends TestWithNode
{
    /** @test */
    public function deleting_non_existing_user_throws(): void
    {
        $this->expectException(UserCommandFailedException::class);

        try {
            $this->manager->deleteUser(UuidGenerator::generateString(), DefaultData::adminCredentials());
        } catch (UserCommandFailedException $e) {
            $this->assertSame(HttpStatusCode::NOT_FOUND, $e->httpStatusCode());

            throw $e;
        }
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function deleting_created_user_deletes_it(): void
    {
        $user = UuidGenerator::generateString();

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
        $name = UuidGenerator::generateString();

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

        $this->expectException(UserCommandFailedException::class);

        $this->manager->getUser($name, DefaultData::adminCredentials());
    }
}
