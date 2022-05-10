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

class read_stream_meta_security extends AuthenticationTestCase
{
    /** @test */
    public function reading_stream_meta_with_not_existing_credentials_is_not_authenticated(): void
    {
        $this->expectException(NotAuthenticated::class);

        $this->readMeta('metaread-stream', 'badlogin', 'badpass');
    }

    /** @test */
    public function reading_stream_meta_with_no_credentials_is_denied(): void
    {
        $this->expectException(AccessDenied::class);

        $this->readMeta('metaread-stream', 'user2', 'pa$$2');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function reading_stream_meta_with_authorized_user_credentials_succeeds(): void
    {
        $this->readMeta('metaread-stream', 'user1', 'pa$$1');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function reading_stream_meta_with_admin_user_credentials_succeeds(): void
    {
        $this->readMeta('metaread-stream', 'adm', 'admpa$$');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function reading_no_acl_stream_meta_succeeds_when_no_credentials_are_passed(): void
    {
        $this->readMeta('noacl-stream', null, null);
    }

    /** @test */
    public function reading_no_acl_stream_meta_is_not_authenticated_when_not_existing_credentials_are_passed(): void
    {
        $this->expectException(NotAuthenticated::class);

        $this->readMeta('noacl-stream', 'badlogin', 'badpass');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function reading_no_acl_stream_meta_succeeds_when_any_existing_user_credentials_are_passed(): void
    {
        $this->readMeta('noacl-stream', 'user1', 'pa$$1');
        $this->readMeta('noacl-stream', 'user2', 'pa$$2');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function reading_no_acl_stream_meta_succeeds_when_admin_user_credentials_are_passed(): void
    {
        $this->readMeta('noacl-stream', 'adm', 'admpa$$');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function reading_all_access_normal_stream_meta_succeeds_when_any_existing_user_credentials_are_passed(): void
    {
        $this->readMeta('normal-all', 'user1', 'pa$$1');
        $this->readMeta('normal-all', 'user2', 'pa$$2');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function reading_all_access_normal_stream_meta_succeeds_when_admin_user_credentials_are_passed(): void
    {
        $this->readMeta('normal-all', 'adm', 'admpa$$');
    }
}
