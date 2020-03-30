<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2020 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2020 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient\UserManagement;

use Generator;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Exception\UserCommandFailed;
use Prooph\EventStore\Transport\Http\HttpStatusCode;
use Prooph\EventStore\UserManagement\UserDetails;
use Prooph\EventStore\Util\Guid;
use ProophTest\EventStoreClient\DefaultData;

class deleting_a_user extends TestWithNode
{
    /**
     * @test
     */
    public function deleting_non_existing_user_throws(): Generator
    {
        yield $this->execute(function (): Generator {
            $this->expectException(UserCommandFailed::class);

            try {
                yield $this->manager->deleteUserAsync(Guid::generateString(), DefaultData::adminCredentials());
            } catch (UserCommandFailed $e) {
                $this->assertSame(HttpStatusCode::NOT_FOUND, $e->httpStatusCode());

                throw $e;
            }
        });
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function deleting_created_user_deletes_it(): Generator
    {
        yield $this->execute(function (): Generator {
            $user = Guid::generateString();

            yield $this->manager->createUserAsync($user, 'ourofull', ['foo', 'bar'], 'ouro', DefaultData::adminCredentials());
            yield $this->manager->deleteUserAsync($user, DefaultData::adminCredentials());
        });
    }

    /**
     * @test
     */
    public function deleting_empty_user_throws(): Generator
    {
        yield $this->execute(function (): Generator {
            $this->expectException(InvalidArgumentException::class);

            yield $this->manager->deleteUserAsync('', DefaultData::adminCredentials());
        });
    }

    /**
     * @test
     */
    public function can_delete_a_user(): Generator
    {
        yield $this->execute(function (): Generator {
            $name = Guid::generateString();

            yield $this->manager->createUserAsync(
                $name,
                'ouro',
                ['foo', 'bar'],
                'ouro',
                DefaultData::adminCredentials()
            );

            $x = yield $this->manager->getUserAsync($name, DefaultData::adminCredentials());

            $this->assertInstanceOf(UserDetails::class, $x);

            yield $this->manager->deleteUserAsync($name, DefaultData::adminCredentials());

            $this->expectException(UserCommandFailed::class);

            yield $this->manager->getUserAsync($name, DefaultData::adminCredentials());
        });
    }
}
