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

use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\StreamAcl;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\UserCredentials;

class stream_security_inheritance extends AuthenticationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $settings = new SystemSettings(
            new StreamAcl([], ['user1']),
            new StreamAcl([], ['user1'])
        );

        $this->connection->setSystemSettings($settings, new UserCredentials('adm', 'admpa$$'));

        $this->connection->setStreamMetadata(
            'user-no-acl',
            ExpectedVersion::Any,
            StreamMetadata::create()->build(),
            new UserCredentials('adm', 'admpa$$')
        );
        $this->connection->setStreamMetadata(
            'user-w-diff',
            ExpectedVersion::Any,
            StreamMetadata::create()->setWriteRoles('user2')->build(),
            new UserCredentials('adm', 'admpa$$')
        );
        $this->connection->setStreamMetadata(
            'user-w-multiple',
            ExpectedVersion::Any,
            StreamMetadata::create()->setWriteRoles('user1', 'user2')->build(),
            new UserCredentials('adm', 'admpa$$')
        );
        $this->connection->setStreamMetadata(
            'user-w-restricted',
            ExpectedVersion::Any,
            StreamMetadata::create()->setWriteRoles('')->build(),
            new UserCredentials('adm', 'admpa$$')
        );
        $this->connection->setStreamMetadata(
            'user-w-all',
            ExpectedVersion::Any,
            StreamMetadata::create()->setWriteRoles(SystemRoles::All)->build(),
            new UserCredentials('adm', 'admpa$$')
        );

        $this->connection->setStreamMetadata(
            'user-r-restricted',
            ExpectedVersion::Any,
            StreamMetadata::create()->setReadRoles('user1')->build(),
            new UserCredentials('adm', 'admpa$$')
        );

        $this->connection->setStreamMetadata(
            '$sys-no-acl',
            ExpectedVersion::Any,
            StreamMetadata::create()->build(),
            new UserCredentials('adm', 'admpa$$')
        );
        $this->connection->setStreamMetadata(
            '$sys-w-diff',
            ExpectedVersion::Any,
            StreamMetadata::create()->setWriteRoles('user2')->build(),
            new UserCredentials('adm', 'admpa$$')
        );
        $this->connection->setStreamMetadata(
            '$sys-w-multiple',
            ExpectedVersion::Any,
            StreamMetadata::create()->setWriteRoles('user1', 'user2')->build(),
            new UserCredentials('adm', 'admpa$$')
        );
        $this->connection->setStreamMetadata(
            '$sys-w-restricted',
            ExpectedVersion::Any,
            StreamMetadata::create()->setWriteRoles('')->build(),
            new UserCredentials('adm', 'admpa$$')
        );
        $this->connection->setStreamMetadata(
            '$sys-w-all',
            ExpectedVersion::Any,
            StreamMetadata::create()->setWriteRoles(SystemRoles::All)->build(),
            new UserCredentials('adm', 'admpa$$')
        );
    }

    /** @test */
    public function acl_inheritance_is_working_properly_on_user_streams(): void
    {
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('user-no-acl', null, null));
        $this->writeStream('user-no-acl', 'user1', 'pa$$1');
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('user-no-acl', 'user2', 'pa$$2'));
        $this->writeStream('user-no-acl', 'adm', 'admpa$$');

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('user-w-diff', null, null));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('user-w-diff', 'user1', 'pa$$1'));
        $this->writeStream('user-w-diff', 'user2', 'pa$$2');
        $this->writeStream('user-w-diff', 'adm', 'admpa$$');

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('user-w-multiple', null, null));
        $this->writeStream('user-w-multiple', 'user1', 'pa$$1');
        $this->writeStream('user-w-multiple', 'user2', 'pa$$2');
        $this->writeStream('user-w-multiple', 'adm', 'admpa$$');

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('user-w-restricted', null, null));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('user-w-restricted', 'user1', 'pa$$1'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('user-w-restricted', 'user2', 'pa$$2'));
        $this->writeStream('user-w-restricted', 'adm', 'admpa$$');

        $this->writeStream('user-w-all', null, null);
        $this->writeStream('user-w-all', 'user1', 'pa$$1');
        $this->writeStream('user-w-all', 'user2', 'pa$$2');
        $this->writeStream('user-w-all', 'adm', 'admpa$$');

        $this->readEvent('user-no-acl', null, null);
        $this->readEvent('user-no-acl', 'user1', 'pa$$1');
        $this->readEvent('user-no-acl', 'user2', 'pa$$2');
        $this->readEvent('user-no-acl', 'adm', 'admpa$$');

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('user-r-restricted', null, null));
        $this->readEvent('user-r-restricted', 'user1', 'pa$$1');
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('user-r-restricted', 'user2', 'pa$$2'));
        $this->readEvent('user-r-restricted', 'adm', 'admpa$$');
    }

    /** @test */
    public function acl_inheritance_is_working_properly_on_system_streams(): void
    {
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$sys-no-acl', null, null));
        $this->writeStream('$sys-no-acl', 'user1', 'pa$$1');
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$sys-no-acl', 'user2', 'pa$$2'));
        $this->writeStream('$sys-no-acl', 'adm', 'admpa$$');

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$sys-w-diff', null, null));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$sys-w-diff', 'user1', 'pa$$1'));
        $this->writeStream('$sys-w-diff', 'user2', 'pa$$2');
        $this->writeStream('$sys-w-diff', 'adm', 'admpa$$');

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$sys-w-multiple', null, null));
        $this->writeStream('$sys-w-multiple', 'user1', 'pa$$1');
        $this->writeStream('$sys-w-multiple', 'user2', 'pa$$2');
        $this->writeStream('$sys-w-multiple', 'adm', 'admpa$$');

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$sys-w-restricted', null, null));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$sys-w-restricted', 'user1', 'pa$$1'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$sys-w-restricted', 'user2', 'pa$$2'));
        $this->writeStream('$sys-w-restricted', 'adm', 'admpa$$');

        $this->writeStream('$sys-w-all', null, null);
        $this->writeStream('$sys-w-all', 'user1', 'pa$$1');
        $this->writeStream('$sys-w-all', 'user2', 'pa$$2');
        $this->writeStream('$sys-w-all', 'adm', 'admpa$$');

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('$sys-no-acl', null, null));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('$sys-no-acl', 'user1', 'pa$$1'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('$sys-no-acl', 'user2', 'pa$$2'));
        $this->readEvent('$sys-no-acl', 'adm', 'admpa$$');
    }
}
