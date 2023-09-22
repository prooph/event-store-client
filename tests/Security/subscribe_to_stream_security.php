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

class subscribe_to_stream_security extends AuthenticationTestCase
{
    /** @test */
    public function subscribing_to_stream_with_not_existing_credentials_is_not_authenticated(): void
    {
        $this->expectException(NotAuthenticated::class);

        $this->subscribeToStream('read-stream', 'badlogin', 'badpass');
    }

    /** @test */
    public function subscribing_to_stream_with_no_credentials_is_denied(): void
    {
        $this->expectException(AccessDenied::class);

        $this->subscribeToStream('read-stream', null, null);
    }

    /** @test */
    public function subscribing_to_stream_with_not_authorized_user_credentials_is_denied(): void
    {
        $this->expectException(AccessDenied::class);

        $this->subscribeToStream('read-stream', 'user2', 'pa$$2');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function reading_stream_with_authorized_user_credentials_succeeds(): void
    {
        $this->subscribeToStream('read-stream', 'user1', 'pa$$1');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function reading_stream_with_admin_user_credentials_succeeds(): void
    {
        $this->subscribeToStream('read-stream', 'adm', 'admpa$$');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function subscribing_to_no_acl_stream_succeeds_when_no_credentials_are_passed(): void
    {
        $this->subscribeToStream('noacl-stream', null, null);
    }

    /** @test */
    public function subscribing_to_no_acl_stream_is_not_authenticated_when_not_existing_credentials_are_passed(): void
    {
        $this->expectException(NotAuthenticated::class);

        $this->subscribeToStream('noacl-stream', 'badlogin', 'badpass');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function subscribing_to_no_acl_stream_succeeds_when_any_existing_user_credentials_are_passed(): void
    {
        $this->subscribeToStream('noacl-stream', 'user1', 'pa$$1');
        $this->subscribeToStream('noacl-stream', 'user2', 'pa$$2');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function subscribing_to_no_acl_stream_succeeds_when_admin_user_credentials_are_passed(): void
    {
        $this->subscribeToStream('noacl-stream', 'adm', 'admpa$$');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function subscribing_to_all_access_normal_stream_succeeds_when_no_credentials_are_passed(): void
    {
        $this->subscribeToStream('normal-all', null, null);
    }

    /** @test */
    public function subscribing_to_all_access_normal_stream_is_not_authenticated_when_not_existing_credentials_are_passed(): void
    {
        $this->expectException(NotAuthenticated::class);

        $this->subscribeToStream('normal-all', 'badlogin', 'badpass');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function subscribing_to_all_access_normal_stream_succeeds_when_any_existing_user_credentials_are_passed(): void
    {
        $this->subscribeToStream('normal-all', 'user1', 'pa$$1');
        $this->subscribeToStream('normal-all', 'user2', 'pa$$2');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function subscribing_to_all_access_normal_streamm_succeeds_when_admin_user_credentials_are_passed(): void
    {
        $this->subscribeToStream('normal-all', 'adm', 'admpa$$');
    }
}
