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
use ProophTest\EventStoreClient\DefaultData;

class creating_a_user extends TestWithNode
{
    /** @test */
    public function creating_a_user_with_empty_username_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager->createUser(
            '',
            'ouro',
            ['foo', 'bar'],
            'foofoofoo'
        );
    }

    /** @test */
    public function creating_a_user_with_empty_name_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager->createUser(
            'ouro',
            '',
            ['foo', 'bar'],
            'foofoofoo'
        );
    }

    /** @test */
    public function creating_a_user_with_empty_password_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager->createUser(
            'ouro',
            'ouro',
            ['foo', 'bar'],
            ''
        );
    }

    /** @test */
    public function fetching_user_with_empty_name_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager->getUser('', DefaultData::adminCredentials());
    }

    /** @test */
    public function creating_a_user_with_parameters_can_be_read(): void
    {
        $this->manager->createUser(
            'ouro',
            'ourofull',
            ['foo', 'bar'],
            'foofoofoo',
            DefaultData::adminCredentials()
        );

        $user = $this->manager->getUser('ouro', DefaultData::adminCredentials());

        $this->assertSame('ouro', $user->loginName());
        $this->assertSame('ourofull', $user->fullName());
        $this->assertEquals(['foo', 'bar'], $user->groups());
    }
}
