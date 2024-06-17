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

class read_stream_security extends AuthenticationTestCase
{
    /** @test */
    public function reading_stream_with_not_existing_credentials_is_not_authenticated(): void
    {
        $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readEvent('read-stream', 'badlogin', 'badpass'));
        $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readStreamForward('read-stream', 'badlogin', 'badpass'));
        $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readStreamBackward('read-stream', 'badlogin', 'badpass'));
    }

    /** @test */
    public function reading_stream_with_no_credentials_is_denied(): void
    {
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('read-stream', null, null));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamForward('read-stream', null, null));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamBackward('read-stream', null, null));
    }

    /** @test */
    public function reading_stream_with_not_authorized_user_credentials_is_denied(): void
    {
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('read-stream', 'user2', 'pa$$2'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamForward('read-stream', 'user2', 'pa$$2'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamBackward('read-stream', 'user2', 'pa$$2'));
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function reading_stream_with_authorized_user_credentials_succeeds(): void
    {
        $this->readEvent('read-stream', 'user1', 'pa$$1');
        $this->readStreamForward('read-stream', 'user1', 'pa$$1');
        $this->readStreamBackward('read-stream', 'user1', 'pa$$1');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function reading_stream_with_admin_user_credentials_succeeds(): void
    {
        $this->readEvent('read-stream', 'adm', 'admpa$$');
        $this->readStreamForward('read-stream', 'adm', 'admpa$$');
        $this->readStreamBackward('read-stream', 'adm', 'admpa$$');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function reading_no_acl_stream_succeeds_when_no_credentials_are_passed(): void
    {
        $this->readEvent('noacl-stream', null, null);
        $this->readStreamForward('noacl-stream', null, null);
        $this->readStreamBackward('noacl-stream', null, null);
    }

    /** @test */
    public function reading_no_acl_stream_is_not_authenticated_when_not_existing_credentials_are_passed(): void
    {
        $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readEvent('noacl-stream', 'badlogin', 'badpass'));
        $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readStreamForward('noacl-stream', 'badlogin', 'badpass'));
        $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readStreamBackward('noacl-stream', 'badlogin', 'badpass'));
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function reading_no_acl_stream_succeeds_when_any_existing_user_credentials_are_passed(): void
    {
        $this->readEvent('noacl-stream', 'user1', 'pa$$1');
        $this->readStreamForward('noacl-stream', 'user1', 'pa$$1');
        $this->readStreamBackward('noacl-stream', 'user1', 'pa$$1');

        $this->readEvent('noacl-stream', 'user2', 'pa$$2');
        $this->readStreamForward('noacl-stream', 'user2', 'pa$$2');
        $this->readStreamBackward('noacl-stream', 'user2', 'pa$$2');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function reading_no_acl_stream_succeeds_when_admin_user_credentials_are_passed(): void
    {
        $this->readEvent('noacl-stream', 'adm', 'admpa$$');
        $this->readStreamForward('noacl-stream', 'adm', 'admpa$$');
        $this->readStreamBackward('noacl-stream', 'adm', 'admpa$$');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function reading_all_access_normal_stream_succeeds_when_no_credentials_are_passed(): void
    {
        $this->readEvent('normal-all', null, null);
        $this->readStreamForward('normal-all', null, null);
        $this->readStreamBackward('normal-all', null, null);
    }

    /** @test */
    public function reading_all_access_normal_stream_is_not_authenticated_when_not_existing_credentials_are_passed(): void
    {
        $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readEvent('normal-all', 'badlogin', 'badpass'));
        $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readStreamForward('normal-all', 'badlogin', 'badpass'));
        $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readStreamBackward('normal-all', 'badlogin', 'badpass'));
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function reading_all_access_normal_stream_succeeds_when_any_existing_user_credentials_are_passed(): void
    {
        $this->readEvent('normal-all', 'user1', 'pa$$1');
        $this->readStreamForward('normal-all', 'user1', 'pa$$1');
        $this->readStreamBackward('normal-all', 'user1', 'pa$$1');

        $this->readEvent('normal-all', 'user2', 'pa$$2');
        $this->readStreamForward('normal-all', 'user2', 'pa$$2');
        $this->readStreamBackward('normal-all', 'user2', 'pa$$2');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function reading_all_access_normal_stream_succeeds_when_admin_user_credentials_are_passed(): void
    {
        $this->readEvent('normal-all', 'adm', 'admpa$$');
        $this->readStreamForward('normal-all', 'adm', 'admpa$$');
        $this->readStreamBackward('normal-all', 'adm', 'admpa$$');
    }
}
