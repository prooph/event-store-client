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

namespace ProophTest\EventStoreClient\Security;

use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\NotAuthenticated;

class subscribe_to_all_security extends AuthenticationTestCase
{
    /** @test */
    public function subscribing_to_all_with_not_existing_credentials_is_not_authenticated(): void
    {
        $this->expectException(NotAuthenticated::class);

        $this->subscribeToAll('badlogin', 'badpass');
    }

    /** @test */
    public function subscribing_to_all_with_no_credentials_is_denied(): void
    {
        $this->expectException(AccessDenied::class);

        $this->subscribeToAll(null, null);
    }

    /** @test */
    public function subscribing_to_all_with_not_authorized_user_credentials_is_denied(): void
    {
        $this->expectException(AccessDenied::class);

        $this->subscribeToAll('user2', 'pa$$2');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function subscribing_to_all_with_authorized_user_credentials_succeeds(): void
    {
        $this->subscribeToAll('user1', 'pa$$1');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function subscribing_to_all_with_admin_user_credentials_succeeds(): void
    {
        $this->subscribeToAll('adm', 'admpa$$');
    }
}
