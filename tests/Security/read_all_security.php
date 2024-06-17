<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2024 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2024 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient\Security;

use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\NotAuthenticated;

class read_all_security extends AuthenticationTestCase
{
    /** @test */
    public function reading_all_with_not_existing_credentials_is_not_authenticated(): void
    {
        $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readAllForward('badlogin', 'badpass'));
        $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readAllBackward('badlogin', 'badpass'));
    }

    /** @test */
    public function reading_all_with_no_credentials_is_denied(): void
    {
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readAllForward(null, null));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readAllBackward(null, null));
    }

    /** @test */
    public function reading_all_with_not_authorized_user_credentials_is_denied(): void
    {
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readAllForward('user2', 'pa$$2'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readAllBackward('user2', 'pa$$2'));
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function reading_all_with_authorized_user_credentials_succeeds(): void
    {
        $this->readAllForward('user1', 'pa$$1');
        $this->readAllBackward('user1', 'pa$$1');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function reading_all_with_admin_credentials_succeeds(): void
    {
        $this->readAllForward('adm', 'admpa$$');
        $this->readAllBackward('adm', 'admpa$$');
    }
}
