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

use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\StreamMetadata;

class delete_stream_security extends AuthenticationTestCase
{
    /** @test */
    public function delete_of_all_is_never_allowed(): void
    {
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->deleteStream('$all', null, null));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->deleteStream('$all', 'user1', 'pa$$1'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->deleteStream('$all', 'adm', 'admpa$$'));
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function deleting_normal_no_acl_stream_with_no_user_is_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(StreamMetadata::create()->build());

        $this->deleteStream($streamId, null, null);
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function deleting_normal_no_acl_stream_with_existing_user_is_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(StreamMetadata::create()->build());

        $this->deleteStream($streamId, 'user1', 'pa$$1');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function deleting_normal_no_acl_stream_with_admin_user_is_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(StreamMetadata::create()->build());

        $this->deleteStream($streamId, 'adm', 'admpa$$');
    }

    /** @test */
    public function deleting_normal_user_stream_with_no_user_is_not_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(
            StreamMetadata::create()
            ->setDeleteRoles('user1')
            ->build()
        );

        $this->expectException(AccessDenied::class);

        $this->deleteStream($streamId, null, null);
    }

    /** @test */
    public function deleting_normal_user_stream_with_not_authorized_user_is_not_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(
            StreamMetadata::create()
            ->setDeleteRoles('user1')
            ->build()
        );

        $this->expectException(AccessDenied::class);

        $this->deleteStream($streamId, 'user2', 'pa$$2');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function deleting_normal_user_stream_with_authorized_user_is_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(
            StreamMetadata::create()
            ->setDeleteRoles('user1')
            ->build()
        );

        $this->deleteStream($streamId, 'user1', 'pa$$1');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function deleting_normal_user_stream_with_admin_user_is_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(
            StreamMetadata::create()
            ->setDeleteRoles('user1')
            ->build()
        );

        $this->deleteStream($streamId, 'adm', 'admpa$$');
    }

    /** @test */
    public function deleting_normal_admin_stream_with_no_user_is_not_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(
            StreamMetadata::create()
            ->setDeleteRoles(SystemRoles::Admins)
            ->build()
        );

        $this->expectException(AccessDenied::class);

        $this->deleteStream($streamId, null, null);
    }

    /** @test */
    public function deleting_normal_admin_stream_with_existing_user_is_not_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(
            StreamMetadata::create()
            ->setDeleteRoles(SystemRoles::Admins)
            ->build()
        );

        $this->expectException(AccessDenied::class);

        $this->deleteStream($streamId, 'user1', 'pa$$1');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function deleting_normal_admin_stream_with_admin_user_is_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(
            StreamMetadata::create()
            ->setDeleteRoles(SystemRoles::Admins)
            ->build()
        );

        $this->deleteStream($streamId, 'adm', 'admpa$$');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function deleting_normal_all_stream_with_no_user_is_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(
            StreamMetadata::create()
            ->setDeleteRoles(SystemRoles::All)
            ->build()
        );

        $this->deleteStream($streamId, null, null);
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function deleting_normal_all_stream_with_existing_user_is_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(
            StreamMetadata::create()
            ->setDeleteRoles(SystemRoles::All)
            ->build()
        );

        $this->deleteStream($streamId, 'user1', 'pa$$1');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function deleting_normal_all_stream_with_admin_user_is_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(
            StreamMetadata::create()
            ->setDeleteRoles(SystemRoles::All)
            ->build()
        );

        $this->deleteStream($streamId, 'adm', 'admpa$$');
    }

    // $-stream

    /** @test */
    public function deleting_system_no_acl_stream_with_no_user_is_not_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(
            StreamMetadata::create()->build(),
            '$'
        );

        $this->expectException(AccessDenied::class);

        $this->deleteStream($streamId, null, null);
    }

    /** @test */
    public function deleting_system_no_acl_stream_with_existing_user_is_not_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(
            StreamMetadata::create()->build(),
            '$'
        );

        $this->expectException(AccessDenied::class);

        $this->deleteStream($streamId, 'user1', 'pa$$1');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function deleting_system_no_acl_stream_with_admin_user_is_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(
            StreamMetadata::create()->build(),
            '$'
        );

        $this->deleteStream($streamId, 'adm', 'admpa$$');
    }

    /** @test */
    public function deleting_system_user_stream_with_no_user_is_not_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(
            StreamMetadata::create()
                ->setDeleteRoles('user1')
                ->build(),
            '$'
        );

        $this->expectException(AccessDenied::class);

        $this->deleteStream($streamId, null, null);
    }

    /** @test */
    public function deleting_system_user_stream_with_not_authorized_user_is_not_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(
            StreamMetadata::create()
                ->setDeleteRoles('user1')
                ->build(),
            '$'
        );

        $this->expectException(AccessDenied::class);

        $this->deleteStream($streamId, 'user2', 'pa$$2');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function deleting_system_user_stream_with_authorized_user_is_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(
            StreamMetadata::create()
                ->setDeleteRoles('user1')
                ->build(),
            '$'
        );

        $this->deleteStream($streamId, 'user1', 'pa$$1');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function deleting_system_user_stream_with_admin_user_is_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(
            StreamMetadata::create()
                ->setDeleteRoles('user1')
                ->build(),
            '$'
        );

        $this->deleteStream($streamId, 'adm', 'admpa$$');
    }

    /** @test */
    public function deleting_system_admin_stream_with_no_user_is_not_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(
            StreamMetadata::create()
                ->setDeleteRoles(SystemRoles::Admins)
                ->build(),
            '$'
        );

        $this->expectException(AccessDenied::class);

        $this->deleteStream($streamId, null, null);
    }

    /** @test */
    public function deleting_system_admin_stream_with_existing_user_is_not_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(
            StreamMetadata::create()
                ->setDeleteRoles(SystemRoles::Admins)
                ->build(),
            '$'
        );

        $this->expectException(AccessDenied::class);

        $this->deleteStream($streamId, 'user1', 'pa$$1');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function deleting_system_admin_stream_with_admin_user_is_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(
            StreamMetadata::create()
                ->setDeleteRoles(SystemRoles::Admins)
                ->build(),
            '$'
        );

        $this->deleteStream($streamId, 'adm', 'admpa$$');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function deleting_system_all_stream_with_no_user_is_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(
            StreamMetadata::create()
                ->setDeleteRoles(SystemRoles::All)
                ->build(),
            '$'
        );

        $this->deleteStream($streamId, null, null);
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function deleting_system_all_stream_with_existing_user_is_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(
            StreamMetadata::create()
                ->setDeleteRoles(SystemRoles::All)
                ->build(),
            '$'
        );

        $this->deleteStream($streamId, 'user1', 'pa$$1');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function deleting_system_all_stream_with_admin_user_is_allowed(): void
    {
        $streamId = $this->createStreamWithMeta(
            StreamMetadata::create()
                ->setDeleteRoles(SystemRoles::All)
                ->build(),
            '$'
        );

        $this->deleteStream($streamId, 'adm', 'admpa$$');
    }
}
