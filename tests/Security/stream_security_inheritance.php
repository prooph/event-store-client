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
use Generator;
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\StreamAcl;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\UserCredentials;
use Throwable;

class stream_security_inheritance extends AuthenticationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        wait(call(function (): Generator {
            $settings = new SystemSettings(
                new StreamAcl([], ['user1']),
                new StreamAcl([], ['user1'])
            );
            yield $this->connection->setSystemSettingsAsync($settings, new UserCredentials('adm', 'admpa$$'));

            yield $this->connection->setStreamMetadataAsync(
                'user-no-acl',
                ExpectedVersion::ANY,
                StreamMetadata::create()->build(),
                new UserCredentials('adm', 'admpa$$')
            );
            yield $this->connection->setStreamMetadataAsync(
                'user-w-diff',
                ExpectedVersion::ANY,
                StreamMetadata::create()->setWriteRoles('user2')->build(),
                new UserCredentials('adm', 'admpa$$')
            );
            yield $this->connection->setStreamMetadataAsync(
                'user-w-multiple',
                ExpectedVersion::ANY,
                StreamMetadata::create()->setWriteRoles('user1', 'user2')->build(),
                new UserCredentials('adm', 'admpa$$')
            );
            yield $this->connection->setStreamMetadataAsync(
                'user-w-restricted',
                ExpectedVersion::ANY,
                StreamMetadata::create()->setWriteRoles('')->build(),
                new UserCredentials('adm', 'admpa$$')
            );
            yield $this->connection->setStreamMetadataAsync(
                'user-w-all',
                ExpectedVersion::ANY,
                StreamMetadata::create()->setWriteRoles(SystemRoles::ALL)->build(),
                new UserCredentials('adm', 'admpa$$')
            );

            yield $this->connection->setStreamMetadataAsync(
                'user-r-restricted',
                ExpectedVersion::ANY,
                StreamMetadata::create()->setReadRoles('user1')->build(),
                new UserCredentials('adm', 'admpa$$')
            );

            yield $this->connection->setStreamMetadataAsync(
                '$sys-no-acl',
                ExpectedVersion::ANY,
                StreamMetadata::create()->build(),
                new UserCredentials('adm', 'admpa$$')
            );
            yield $this->connection->setStreamMetadataAsync(
                '$sys-w-diff',
                ExpectedVersion::ANY,
                StreamMetadata::create()->setWriteRoles('user2')->build(),
                new UserCredentials('adm', 'admpa$$')
            );
            yield $this->connection->setStreamMetadataAsync(
                '$sys-w-multiple',
                ExpectedVersion::ANY,
                StreamMetadata::create()->setWriteRoles('user1', 'user2')->build(),
                new UserCredentials('adm', 'admpa$$')
            );
            yield $this->connection->setStreamMetadataAsync(
                '$sys-w-restricted',
                ExpectedVersion::ANY,
                StreamMetadata::create()->setWriteRoles('')->build(),
                new UserCredentials('adm', 'admpa$$')
            );
            yield $this->connection->setStreamMetadataAsync(
                '$sys-w-all',
                ExpectedVersion::ANY,
                StreamMetadata::create()->setWriteRoles(SystemRoles::ALL)->build(),
                new UserCredentials('adm', 'admpa$$')
            );
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function acl_inheritance_is_working_properly_on_user_streams(): void
    {
        wait(call(function () {
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('user-no-acl', null, null));
            yield $this->writeStream('user-no-acl', 'user1', 'pa$$1');
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('user-no-acl', 'user2', 'pa$$2'));
            yield $this->writeStream('user-no-acl', 'adm', 'admpa$$');

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('user-w-diff', null, null));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('user-w-diff', 'user1', 'pa$$1'));
            yield $this->writeStream('user-w-diff', 'user2', 'pa$$2');
            yield $this->writeStream('user-w-diff', 'adm', 'admpa$$');

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('user-w-multiple', null, null));
            yield $this->writeStream('user-w-multiple', 'user1', 'pa$$1');
            yield $this->writeStream('user-w-multiple', 'user2', 'pa$$2');
            yield $this->writeStream('user-w-multiple', 'adm', 'admpa$$');

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('user-w-restricted', null, null));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('user-w-restricted', 'user1', 'pa$$1'));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('user-w-restricted', 'user2', 'pa$$2'));
            yield $this->writeStream('user-w-restricted', 'adm', 'admpa$$');

            yield $this->writeStream('user-w-all', null, null);
            yield $this->writeStream('user-w-all', 'user1', 'pa$$1');
            yield $this->writeStream('user-w-all', 'user2', 'pa$$2');
            yield $this->writeStream('user-w-all', 'adm', 'admpa$$');

            yield $this->readEvent('user-no-acl', null, null);
            yield $this->readEvent('user-no-acl', 'user1', 'pa$$1');
            yield $this->readEvent('user-no-acl', 'user2', 'pa$$2');
            yield $this->readEvent('user-no-acl', 'adm', 'admpa$$');

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('user-r-restricted', null, null));
            yield $this->readEvent('user-r-restricted', 'user1', 'pa$$1');
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('user-r-restricted', 'user2', 'pa$$2'));
            yield $this->readEvent('user-r-restricted', 'adm', 'admpa$$');
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function acl_inheritance_is_working_properly_on_system_streams(): void
    {
        wait(call(function () {
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$sys-no-acl', null, null));
            yield $this->writeStream('$sys-no-acl', 'user1', 'pa$$1');
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$sys-no-acl', 'user2', 'pa$$2'));
            yield $this->writeStream('$sys-no-acl', 'adm', 'admpa$$');

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$sys-w-diff', null, null));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$sys-w-diff', 'user1', 'pa$$1'));
            yield $this->writeStream('$sys-w-diff', 'user2', 'pa$$2');
            yield $this->writeStream('$sys-w-diff', 'adm', 'admpa$$');

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$sys-w-multiple', null, null));
            yield $this->writeStream('$sys-w-multiple', 'user1', 'pa$$1');
            yield $this->writeStream('$sys-w-multiple', 'user2', 'pa$$2');
            yield $this->writeStream('$sys-w-multiple', 'adm', 'admpa$$');

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$sys-w-restricted', null, null));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$sys-w-restricted', 'user1', 'pa$$1'));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$sys-w-restricted', 'user2', 'pa$$2'));
            yield $this->writeStream('$sys-w-restricted', 'adm', 'admpa$$');

            yield $this->writeStream('$sys-w-all', null, null);
            yield $this->writeStream('$sys-w-all', 'user1', 'pa$$1');
            yield $this->writeStream('$sys-w-all', 'user2', 'pa$$2');
            yield $this->writeStream('$sys-w-all', 'adm', 'admpa$$');

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('$sys-no-acl', null, null));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('$sys-no-acl', 'user1', 'pa$$1'));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('$sys-no-acl', 'user2', 'pa$$2'));
            yield $this->readEvent('$sys-no-acl', 'adm', 'admpa$$');
        }));
    }
}
