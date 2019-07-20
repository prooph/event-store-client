<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2019 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2019 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
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

class read_all_security extends AuthenticationTestCase
{
    /**
     * @test
     * @throws Throwable
     */
    public function reading_all_with_not_existing_credentials_is_not_authenticated(): void
    {
        wait(call(function () {
            yield $this->expectExceptionFromCallback(NotAuthenticated::class, function () {
                return $this->readAllForward('badlogin', 'badpass');
            });
            yield $this->expectExceptionFromCallback(NotAuthenticated::class, function () {
                return $this->readAllBackward('badlogin', 'badpass');
            });
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_all_with_no_credentials_is_denied(): void
    {
        wait(call(function () {
            yield $this->expectExceptionFromCallback(AccessDenied::class, function () {
                return $this->readAllForward(null, null);
            });
            yield $this->expectExceptionFromCallback(AccessDenied::class, function () {
                return $this->readAllBackward(null, null);
            });
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_all_with_not_authorized_user_credentials_is_denied(): void
    {
        wait(call(function () {
            yield $this->expectExceptionFromCallback(AccessDenied::class, function () {
                return $this->readAllForward('user2', 'pa$$2');
            });
            yield $this->expectExceptionFromCallback(AccessDenied::class, function () {
                return $this->readAllBackward('user2', 'pa$$2');
            });
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_all_with_authorized_user_credentials_succeeds(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(function () {
                return call(function () {
                    yield $this->readAllForward('user1', 'pa$$1');
                    yield $this->readAllBackward('user1', 'pa$$1');
                });
            });
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_all_with_admin_credentials_succeeds(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(function () {
                return call(function () {
                    yield $this->readAllForward('adm', 'admpa$$');
                    yield $this->readAllBackward('adm', 'admpa$$');
                });
            });
        }));
    }
}
