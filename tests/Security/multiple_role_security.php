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
use Prooph\EventStore\StreamAcl;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\UserCredentials;

class multiple_role_security extends AuthenticationTestCase
{
    /** @test */
    public function multiple_roles_are_handled_correctly(): void
    {
        $settings = new SystemSettings(
            new StreamAcl(
                ['user1', 'user2'],
                ['$admins', 'user1'],
                ['user1', SystemRoles::All]
            ),
            null
        );
        $this->connection->setSystemSettings($settings, new UserCredentials('adm', 'admpa$$'));

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('usr-stream', null, null));

        $this->readEvent('usr-stream', 'user1', 'pa$$1');
        $this->readEvent('usr-stream', 'user2', 'pa$$2');
        $this->readEvent('usr-stream', 'adm', 'admpa$$');

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('user-stream', null, null));
        $this->writeStream('usr-stream', 'user1', 'pa$$1');
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('usr-stream', 'user2', 'pa$$2'));
        $this->writeStream('usr-stream', 'adm', 'admpa$$');

        $this->deleteStream('usr-stream1', null, null);
        $this->deleteStream('usr-stream2', 'user1', 'pa$$1');
        $this->deleteStream('usr-stream3', 'user2', 'pa$$2');
        $this->deleteStream('usr-stream4', 'adm', 'admpa$$');

        $this->connection->setSystemSettings(new SystemSettings(), new UserCredentials(
            'adm',
            'admpa$$'
        ));
    }
}
