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

class read_stream_meta_security extends AuthenticationTestCase
{
    /**
     * @test
     * @throws Throwable
     */
    public function reading_stream_meta_with_not_existing_credentials_is_not_authenticated(): void
    {
        wait(call(function () {
            yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readMeta('metaread-stream', 'badlogin', 'badpass'));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_stream_meta_with_no_credentials_is_denied(): void
    {
        wait(call(function () {
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readMeta('metaread-stream', 'user2', 'pa$$2'));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_stream_meta_with_authorized_user_credentials_succeeds(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => $this->readMeta('metaread-stream', 'user1', 'pa$$1'));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_stream_meta_with_admin_user_credentials_succeeds(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => $this->readMeta('metaread-stream', 'adm', 'admpa$$'));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_no_acl_stream_meta_succeeds_when_no_credentials_are_passed(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => $this->readMeta('noacl-stream', null, null));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_no_acl_stream_meta_is_not_authenticated_when_not_existing_credentials_are_passed(): void
    {
        wait(call(function () {
            yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readMeta('noacl-stream', 'badlogin', 'badpass'));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_no_acl_stream_meta_succeeds_when_any_existing_user_credentials_are_passed(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => call(function () {
                yield $this->readMeta('noacl-stream', 'user1', 'pa$$1');
                yield $this->readMeta('noacl-stream', 'user2', 'pa$$2');
            }));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_no_acl_stream_meta_succeeds_when_admin_user_credentials_are_passed(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => $this->readMeta('noacl-stream', 'adm', 'admpa$$'));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_all_access_normal_stream_meta_succeeds_when_any_existing_user_credentials_are_passed(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => call(function () {
                yield $this->readMeta('normal-all', 'user1', 'pa$$1');
                yield $this->readMeta('normal-all', 'user2', 'pa$$2');
            }));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_all_access_normal_stream_meta_succeeds_when_admin_user_credentials_are_passed(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => $this->readMeta('normal-all', 'adm', 'admpa$$'));
        }));
    }
}
