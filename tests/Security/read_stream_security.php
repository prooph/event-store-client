<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2020 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2020 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient\Security;

use function Amp\call;
use function Amp\Promise\wait;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\NotAuthenticated;
use Throwable;

class read_stream_security extends AuthenticationTestCase
{
    /**
     * @test
     * @throws Throwable
     */
    public function reading_stream_with_not_existing_credentials_is_not_authenticated(): void
    {
        wait(call(function () {
            yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readEvent('read-stream', 'badlogin', 'badpass'));
            yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readStreamForward('read-stream', 'badlogin', 'badpass'));
            yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readStreamBackward('read-stream', 'badlogin', 'badpass'));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_stream_with_no_credentials_is_denied(): void
    {
        wait(call(function () {
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('read-stream', null, null));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamForward('read-stream', null, null));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamBackward('read-stream', null, null));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_stream_with_not_authorized_user_credentials_is_denied(): void
    {
        wait(call(function () {
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('read-stream', 'user2', 'pa$$2'));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamForward('read-stream', 'user2', 'pa$$2'));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamBackward('read-stream', 'user2', 'pa$$2'));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_stream_with_authorized_user_credentials_succeeds(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => call(function () {
                yield $this->readEvent('read-stream', 'user1', 'pa$$1');
                yield $this->readStreamForward('read-stream', 'user1', 'pa$$1');
                yield $this->readStreamBackward('read-stream', 'user1', 'pa$$1');
            }));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_stream_with_admin_user_credentials_succeeds(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => call(function () {
                yield $this->readEvent('read-stream', 'adm', 'admpa$$');
                yield $this->readStreamForward('read-stream', 'adm', 'admpa$$');
                yield $this->readStreamBackward('read-stream', 'adm', 'admpa$$');
            }));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_no_acl_stream_succeeds_when_no_credentials_are_passed(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => call(function () {
                yield $this->readEvent('noacl-stream', null, null);
                yield $this->readStreamForward('noacl-stream', null, null);
                yield $this->readStreamBackward('noacl-stream', null, null);
            }));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_no_acl_stream_is_not_authenticated_when_not_existing_credentials_are_passed(): void
    {
        wait(call(function () {
            yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readEvent('noacl-stream', 'badlogin', 'badpass'));
            yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readStreamForward('noacl-stream', 'badlogin', 'badpass'));
            yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readStreamBackward('noacl-stream', 'badlogin', 'badpass'));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_no_acl_stream_succeeds_when_any_existing_user_credentials_are_passed(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => call(function () {
                yield $this->readEvent('noacl-stream', 'user1', 'pa$$1');
                yield $this->readStreamForward('noacl-stream', 'user1', 'pa$$1');
                yield $this->readStreamBackward('noacl-stream', 'user1', 'pa$$1');

                yield $this->readEvent('noacl-stream', 'user2', 'pa$$2');
                yield $this->readStreamForward('noacl-stream', 'user2', 'pa$$2');
                yield $this->readStreamBackward('noacl-stream', 'user2', 'pa$$2');
            }));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_no_acl_stream_succeeds_when_admin_user_credentials_are_passed(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => call(function () {
                yield $this->readEvent('noacl-stream', 'adm', 'admpa$$');
                yield $this->readStreamForward('noacl-stream', 'adm', 'admpa$$');
                yield $this->readStreamBackward('noacl-stream', 'adm', 'admpa$$');
            }));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_all_access_normal_stream_succeeds_when_no_credentials_are_passed(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => call(function () {
                yield $this->readEvent('normal-all', null, null);
                yield $this->readStreamForward('normal-all', null, null);
                yield $this->readStreamBackward('normal-all', null, null);
            }));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_all_access_normal_stream_is_not_authenticated_when_not_existing_credentials_are_passed(): void
    {
        wait(call(function () {
            yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readEvent('normal-all', 'badlogin', 'badpass'));
            yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readStreamForward('normal-all', 'badlogin', 'badpass'));
            yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readStreamBackward('normal-all', 'badlogin', 'badpass'));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_all_access_normal_stream_succeeds_when_any_existing_user_credentials_are_passed(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => call(function () {
                yield $this->readEvent('normal-all', 'user1', 'pa$$1');
                yield $this->readStreamForward('normal-all', 'user1', 'pa$$1');
                yield $this->readStreamBackward('normal-all', 'user1', 'pa$$1');

                yield $this->readEvent('normal-all', 'user2', 'pa$$2');
                yield $this->readStreamForward('normal-all', 'user2', 'pa$$2');
                yield $this->readStreamBackward('normal-all', 'user2', 'pa$$2');
            }));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_all_access_normal_stream_succeeds_when_admin_user_credentials_are_passed(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => call(function () {
                yield $this->readEvent('normal-all', 'adm', 'admpa$$');
                yield $this->readStreamForward('normal-all', 'adm', 'admpa$$');
                yield $this->readStreamBackward('normal-all', 'adm', 'admpa$$');
            }));
        }));
    }
}
