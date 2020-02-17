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

class subscribe_to_all_security extends AuthenticationTestCase
{
    /**
     * @test
     * @throws Throwable
     */
    public function subscribing_to_all_with_not_existing_credentials_is_not_authenticated(): void
    {
        wait(call(function () {
            yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->subscribeToAll('badlogin', 'badpass'));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function subscribing_to_all_with_no_credentials_is_denied(): void
    {
        wait(call(function () {
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->subscribeToAll(null, null));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function subscribing_to_all_with_not_authorized_user_credentials_is_denied(): void
    {
        wait(call(function () {
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->subscribeToAll('user2', 'pa$$2'));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function subscribing_to_all_with_authorized_user_credentials_succeeds(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => $this->subscribeToAll('user1', 'pa$$1'));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function subscribing_to_all_with_admin_user_credentials_succeeds(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => $this->subscribeToAll('adm', 'admpa$$'));
        }));
    }
}
