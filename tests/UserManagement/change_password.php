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
use Prooph\EventStoreClient\UserCredentials;
use ProophTest\EventStoreClient\DefaultData;

class change_password extends TestWithUser
{
    /** @test */
    public function empty_username_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager->changePassword('', 'oldPassword', 'newPassword', DefaultData::adminCredentials());
    }

    /** @test */
    public function empty_current_password_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager->changePassword($this->username, '', 'newPassword', DefaultData::adminCredentials());
    }

    /** @test */
    public function empty_new_password_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager->changePassword($this->username, 'oldPassword', '', DefaultData::adminCredentials());
    }

    /** @test */
    public function can_change_password(): void
    {
        $this->manager->changePassword($this->username, 'password', 'fubar', new UserCredentials($this->username, 'password'));

        $this->expectException(UserCommandFailedException::class);

        try {
            $this->manager->changePassword($this->username, 'password', 'foobar', new UserCredentials($this->username, 'password'));
        } catch (UserCommandFailedException $e) {
            $this->assertSame(HttpStatusCode::UNAUTHORIZED, $e->httpStatusCode());

            throw $e;
        }
    }
}
