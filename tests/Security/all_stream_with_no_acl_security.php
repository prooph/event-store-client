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
use Throwable;

class all_stream_with_no_acl_security extends AuthenticationTestCase
{
    /**
     * @test
     * @throws Throwable
     */
    public function write_to_all_is_never_allowed(): void
    {
        wait(call(function () {
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$all', null, null));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$all', 'user1', 'pa$$1'));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$all', 'adm', 'admpa$$'));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function delete_of_all_is_never_allowed(): void
    {
        wait(call(function () {
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->deleteStream('$all', null, null));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->deleteStream('$all', 'user1', 'pa$$1'));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->deleteStream('$all', 'adm', 'admpa$$'));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_and_subscribing_is_not_allowed_when_no_credentials_are_passed(): void
    {
        wait(call(function () {
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('$all', null, null));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamForward('$all', null, null));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamBackward('$all', null, null));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readMeta('$all', null, null));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->subscribeToStream('$all', null, null));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_and_subscribing_is_not_allowed_for_usual_user(): void
    {
        wait(call(function () {
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('$all', 'user2', 'pa$$2'));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamForward('$all', 'user2', 'pa$$2'));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamBackward('$all', 'user2', 'pa$$2'));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readMeta('$all', 'user2', 'pa$$2'));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->subscribeToStream('$all', 'user2', 'pa$$2'));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_and_subscribing_is_allowed_for_admin_user(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => call(function () {
                yield $this->readEvent('$all', 'adm', 'admpa$$');
                yield $this->readStreamForward('$all', 'adm', 'admpa$$');
                yield $this->readStreamBackward('$all', 'adm', 'admpa$$');
                yield $this->readMeta('$all', 'adm', 'admpa$$');
                yield $this->subscribeToStream('$all', 'adm', 'admpa$$');
            }));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function meta_write_is_not_allowed_when_no_credentials_are_passed(): void
    {
        wait(call(function () {
            $this->expectException(AccessDenied::class);
            yield $this->writeMeta('$all', null, null, null);
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function meta_write_is_not_allowed_for_usual_user(): void
    {
        wait(call(function () {
            $this->expectException(AccessDenied::class);
            yield $this->writeMeta('$all', 'user1', 'pa$$1', null);
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function meta_write_is_allowed_for_admin_user(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => $this->writeMeta('$all', 'adm', 'admpa$$', null));
        }));
    }
}
