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
use Prooph\EventStoreClient\UserCredentials;
use ProophTest\EventStoreClient\DefaultData;
use Throwable;

class enable_disable_user extends TestWithUser
{
    /**
     * @test
     * @throws Throwable
     */
    public function disable_empty_username_throws(): void
    {
        $this->execute(function () {
            $this->expectException(InvalidArgumentException::class);

            yield $this->manager->disableAsync('', DefaultData::adminCredentials());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function enable_empty_username_throws(): void
    {
        $this->execute(function () {
            $this->expectException(InvalidArgumentException::class);

            yield $this->manager->enableAsync('', DefaultData::adminCredentials());
        });
    }

    /**
     * @test
     * @throws Throwable
     */
    public function can_enable_disable_user(): void
    {
        $this->execute(function () {
            yield $this->manager->disableAsync($this->username, DefaultData::adminCredentials());

            $thrown = false;

            try {
                yield $this->manager->disableAsync('foo', DefaultData::adminCredentials());
            } catch (UserCommandFailedException $e) {
                $thrown = true;
            }

            $this->assertTrue($thrown, UserCommandFailedException::class . ' was expected');

            yield $this->manager->enableAsync($this->username, DefaultData::adminCredentials());

            yield $this->manager->getCurrentUserAsync(new UserCredentials($this->username, 'password'));
        });
    }
}
