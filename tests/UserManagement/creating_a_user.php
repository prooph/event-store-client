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

use Exception;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\UserManagement\UserDetails;
use Prooph\EventStoreClient\Util\Guid;
use ProophTest\EventStoreClient\DefaultData;
use Throwable;

class creating_a_user extends TestWithNode
{
    /**
     * @test
     * @throws Throwable
     */
    public function creating_a_user_with_empty_username_throws(): void
    {
        $this->execute(function () {
            $this->expectException(InvalidArgumentException::class);

            yield $this->manager->createUserAsync(
                '',
                'ouro',
                ['foo', 'bar'],
                'foofoofoo'
            );
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function creating_a_user_with_empty_name_throws(): void
    {
        $this->execute(function () {
            $this->expectException(InvalidArgumentException::class);

            yield $this->manager->createUserAsync(
                'ouro',
                '',
                ['foo', 'bar'],
                'foofoofoo'
            );
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function creating_a_user_with_empty_password_throws(): void
    {
        $this->execute(function () {
            $this->expectException(InvalidArgumentException::class);

            yield $this->manager->createUserAsync(
                'ouro',
                'ouro',
                ['foo', 'bar'],
                ''
            );
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function fetching_user_with_empty_name_throws(): void
    {
        $this->execute(function () {
            $this->expectException(InvalidArgumentException::class);

            yield $this->manager->getUserAsync('', DefaultData::adminCredentials());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function creating_a_user_with_parameters_can_be_read(): void
    {
        $this->execute(function () {
            $login = Guid::generateString();

            yield $this->manager->createUserAsync(
                $login,
                'ourofull',
                ['foo', 'bar'],
                'foofoofoo',
                DefaultData::adminCredentials()
            );

            $user = yield $this->manager->getUserAsync($login, DefaultData::adminCredentials());
            \assert($user instanceof UserDetails);

            $this->assertSame($login, $user->loginName());
            $this->assertSame('ourofull', $user->fullName());
            $this->assertEquals(['foo', 'bar'], $user->groups());
            $this->assertCount(5, $user->links());
            $this->assertNull($user->dateLastUpdated());
            $this->assertFalse($user->disabled());

            $this->assertStringEndsWith('/users/' . $login . '/command/reset-password', $user->links()[0]->href());
            $this->assertEquals('reset-password', $user->links()[0]->rel());

            $this->assertStringEndsWith('/users/' . $login . '/command/change-password', $user->links()[1]->href());
            $this->assertEquals('change-password', $user->links()[1]->rel());

            $this->assertStringEndsWith('/users/' . $login, $user->links()[2]->href());
            $this->assertEquals('edit', $user->links()[2]->rel());

            $this->assertStringEndsWith('/users/' . $login, $user->links()[3]->href());
            $this->assertEquals('delete', $user->links()[3]->rel());

            $this->assertStringEndsWith('/users/' . $login . '/command/disable', $user->links()[4]->href());
            $this->assertEquals('disable', $user->links()[4]->rel());

            $href = $user->getRelLink('disable');
            $this->assertStringEndsWith('/users/' . $login . '/command/disable', $href);

            $this->expectException(Exception::class);
            $user->getRelLink('unknown');
        });
    }
}
