<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
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
use Throwable;

class all_stream_with_no_acl_security extends AuthenticationTestCase
{
    /**
     * @test
     * @throws Throwable
     */
    public function write_to_all_is_never_allowed_1(): void
    {
        wait(call(function () {
            $this->expectException(AccessDenied::class);
            yield $this->writeStream('$all', null, null);
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function write_to_all_is_never_allowed_2(): void
    {
        wait(call(function () {
            $this->expectException(AccessDenied::class);
            yield $this->writeStream('$all', 'user1', 'pa$$1');
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function write_to_all_is_never_allowed_3(): void
    {
        wait(call(function () {
            $this->expectException(AccessDenied::class);
            yield $this->writeStream('$all', 'adm', 'admpa$$');
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function delete_of_all_is_never_allowed_1(): void
    {
        wait(call(function () {
            $this->expectException(AccessDenied::class);
            yield $this->deleteStream('$all', null, null);
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function delete_of_all_is_never_allowed_2(): void
    {
        wait(call(function () {
            $this->expectException(AccessDenied::class);
            yield $this->deleteStream('$all', 'user1', 'pa$$1');
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function delete_of_all_is_never_allowed_3(): void
    {
        wait(call(function () {
            $this->expectException(AccessDenied::class);
            yield $this->deleteStream('$all', 'adm', 'admpa$$');
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_and_subscribing_is_not_allowed_when_no_credentials_are_passed_1(): void
    {
        wait(call(function () {
            $this->expectException(AccessDenied::class);
            yield $this->readEvent('$all', null, null);
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_and_subscribing_is_not_allowed_when_no_credentials_are_passed_2(): void
    {
        wait(call(function () {
            $this->expectException(AccessDenied::class);
            yield $this->readStreamForward('$all', null, null);
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_and_subscribing_is_not_allowed_when_no_credentials_are_passed_3(): void
    {
        wait(call(function () {
            $this->expectException(AccessDenied::class);
            yield $this->readStreamBackward('$all', null, null);
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_and_subscribing_is_not_allowed_when_no_credentials_are_passed_4(): void
    {
        wait(call(function () {
            $this->expectException(AccessDenied::class);
            yield $this->readMeta('$all', null, null);
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function reading_and_subscribing_is_not_allowed_when_no_credentials_are_passed_5(): void
    {
        wait(call(function () {
            $this->expectException(AccessDenied::class);
            yield $this->subscribeToStream('$all', null, null);
        }));
    }
}
