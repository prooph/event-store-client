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

use function Amp\call;
use Generator;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\NotAuthenticated;

class read_all_security extends AuthenticationTestCase
{
    /** @test */
    public function reading_all_with_not_existing_credentials_is_not_authenticated(): Generator
    {
        yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readAllForward('badlogin', 'badpass'));
        yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readAllBackward('badlogin', 'badpass'));
    }

    /** @test */
    public function reading_all_with_no_credentials_is_denied(): Generator
    {
        yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readAllForward(null, null));
        yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readAllBackward(null, null));
    }

    /** @test */
    public function reading_all_with_not_authorized_user_credentials_is_denied(): Generator
    {
        yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readAllForward('user2', 'pa$$2'));
        yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readAllBackward('user2', 'pa$$2'));
    }

    /** @test */
    public function reading_all_with_authorized_user_credentials_succeeds(): Generator
    {
        yield $this->expectNoExceptionFromCallback(fn () => call(function (): Generator {
            yield $this->readAllForward('user1', 'pa$$1');
            yield $this->readAllBackward('user1', 'pa$$1');
        }));
    }

    /** @test */
    public function reading_all_with_admin_credentials_succeeds(): Generator
    {
        yield $this->expectNoExceptionFromCallback(fn () => call(function (): Generator {
            yield $this->readAllForward('adm', 'admpa$$');
            yield $this->readAllBackward('adm', 'admpa$$');
        }));
    }
}
