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

class all_stream_with_no_acl_security extends AuthenticationTestCase
{
    /** @test */
    public function write_to_all_is_never_allowed(): void
    {
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$all', null, null));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$all', 'user1', 'pa$$1'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$all', 'adm', 'admpa$$'));
    }

    /** @test */
    public function delete_of_all_is_never_allowed(): void
    {
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->deleteStream('$all', null, null));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->deleteStream('$all', 'user1', 'pa$$1'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->deleteStream('$all', 'adm', 'admpa$$'));
    }

    /** @test */
    public function reading_and_subscribing_is_not_allowed_when_no_credentials_are_passed(): void
    {
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('$all', null, null));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamForward('$all', null, null));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamBackward('$all', null, null));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readMeta('$all', null, null));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->subscribeToStream('$all', null, null));
    }

    /** @test */
    public function reading_and_subscribing_is_not_allowed_for_usual_user(): void
    {
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('$all', 'user2', 'pa$$2'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamForward('$all', 'user2', 'pa$$2'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamBackward('$all', 'user2', 'pa$$2'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readMeta('$all', 'user2', 'pa$$2'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->subscribeToStream('$all', 'user2', 'pa$$2'));
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function reading_and_subscribing_is_allowed_for_admin_user(): void
    {
        $this->readEvent('$all', 'adm', 'admpa$$');
        $this->readStreamForward('$all', 'adm', 'admpa$$');
        $this->readStreamBackward('$all', 'adm', 'admpa$$');
        $this->readMeta('$all', 'adm', 'admpa$$');
        $this->subscribeToStream('$all', 'adm', 'admpa$$');
    }

    /** @test */
    public function meta_write_is_not_allowed_when_no_credentials_are_passed(): void
    {
        $this->expectException(AccessDenied::class);

        $this->writeMeta('$all', null, null, null);
    }

    /** @test */
    public function meta_write_is_not_allowed_for_usual_user(): void
    {
        $this->expectException(AccessDenied::class);

        $this->writeMeta('$all', 'user1', 'pa$$1', null);
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function meta_write_is_allowed_for_admin_user(): void
    {
        $this->writeMeta('$all', 'adm', 'admpa$$', null);
    }
}
