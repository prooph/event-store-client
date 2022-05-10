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

use Generator;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Exception\UserCommandFailed;
use Prooph\EventStore\UserCredentials;
use ProophTest\EventStoreClient\DefaultData;

class enable_disable_user extends TestWithUser
{
    /** @test */
    public function disable_empty_username_throws(): Generator
    {
        $this->expectException(InvalidArgumentException::class);

        yield $this->manager->disableAsync('', DefaultData::adminCredentials());
    }

    /** @test */
    public function enable_empty_username_throws(): Generator
    {
        $this->expectException(InvalidArgumentException::class);

        yield $this->manager->enableAsync('', DefaultData::adminCredentials());
    }

    /** @test */
    public function can_enable_disable_user(): Generator
    {
        yield $this->manager->disableAsync($this->username, DefaultData::adminCredentials());

        $thrown = false;

        try {
            yield $this->manager->disableAsync('foo', DefaultData::adminCredentials());
        } catch (UserCommandFailed $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown, UserCommandFailed::class . ' was expected');

        yield $this->manager->enableAsync($this->username, DefaultData::adminCredentials());

        yield $this->manager->getCurrentUserAsync(new UserCredentials($this->username, 'password'));
    }
}
