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

class write_stream_security extends AuthenticationTestCase
{
    /** @test */
    public function writing_to_all_is_never_allowed(): void
    {
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$all', null, null));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$all', 'user1', 'pa$$1'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$all', 'adm', 'admpa$$'));
    }

    /** @test */
    public function writing_with_not_existing_credentials_is_not_authenticated(): void
    {
        $this->expectException(NotAuthenticated::class);

        $this->writeStream('write-stream', 'badlogin', 'badpass');
    }

    /** @test */
    public function writing_to_stream_with_no_credentials_is_denied(): void
    {
        $this->expectException(AccessDenied::class);

        $this->writeStream('write-stream', null, null);
    }

    /** @test */
    public function writing_to_stream_with_not_authorized_user_credentials_is_denied(): void
    {
        $this->expectException(AccessDenied::class);

        $this->writeStream('write-stream', 'user2', 'pa$$2');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function writing_to_stream_with_authorized_user_credentials_succeeds(): void
    {
        $this->writeStream('write-stream', 'user1', 'pa$$1');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function writing_to_stream_with_admin_user_credentials_succeeds(): void
    {
        $this->writeStream('write-stream', 'adm', 'admpa$$');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function writing_to_no_acl_stream_succeeds_when_no_credentials_are_passed(): void
    {
        $this->writeStream('noacl-stream', null, null);
    }

    /** @test */
    public function writing_to_no_acl_stream_is_not_authenticated_when_not_existing_credentials_are_passed(): void
    {
        $this->expectException(NotAuthenticated::class);

        $this->writeStream('noacl-stream', 'badlogin', 'badpass');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function writing_to_no_acl_stream_succeeds_when_any_existing_user_credentials_are_passed(): void
    {
        $this->writeStream('noacl-stream', 'user1', 'pa$$1');
        $this->writeStream('noacl-stream', 'user2', 'pa$$2');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function writing_to_no_acl_stream_succeeds_when_any_admin_user_credentials_are_passed(): void
    {
        $this->writeStream('noacl-stream', 'adm', 'admpa$$');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function writing_to_all_access_normal_stream_succeeds_when_no_credentials_are_passed(): void
    {
        $this->writeStream('normal-all', null, null);
    }

    /** @test */
    public function writing_to_all_access_normal_stream_is_not_authenticated_when_not_existing_credentials_are_passed(): void
    {
        $this->expectException(NotAuthenticated::class);

        $this->writeStream('normal-all', 'badlogin', 'badpass');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function writing_to_all_access_normal_stream_succeeds_when_any_existing_user_credentials_are_passed(): void
    {
        $this->writeStream('normal-all', 'user1', 'pa$$1');
        $this->writeStream('normal-all', 'user2', 'pa$$2');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function writing_to_all_access_normal_stream_succeeds_when_any_admin_user_credentials_are_passed(): void
    {
        $this->writeStream('normal-all', 'adm', 'admpa$$');
    }
}
