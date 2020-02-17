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
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\StreamAcl;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\UserCredentials;
use Throwable;

class multiple_role_security extends AuthenticationTestCase
{
    /**
     * @test
     * @throws Throwable
     */
    public function multiple_roles_are_handled_correctly(): void
    {
        wait(call(function () {
            $settings = new SystemSettings(
                new StreamAcl(
                    ['user1', 'user2'],
                    ['$admins', 'user1'],
                    ['user1', SystemRoles::ALL]
                ),
                null
            );
            yield $this->connection->setSystemSettingsAsync($settings, new UserCredentials('adm', 'admpa$$'));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('usr-stream', null, null));

            yield $this->readEvent('usr-stream', 'user1', 'pa$$1');
            yield $this->readEvent('usr-stream', 'user2', 'pa$$2');
            yield $this->readEvent('usr-stream', 'adm', 'admpa$$');

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('user-stream', null, null));
            yield $this->writeStream('usr-stream', 'user1', 'pa$$1');
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('usr-stream', 'user2', 'pa$$2'));
            yield $this->writeStream('usr-stream', 'adm', 'admpa$$');

            yield $this->deleteStream('usr-stream1', null, null);
            yield $this->deleteStream('usr-stream2', 'user1', 'pa$$1');
            yield $this->deleteStream('usr-stream3', 'user2', 'pa$$2');
            yield $this->deleteStream('usr-stream4', 'adm', 'admpa$$');

            yield $this->connection->setSystemSettingsAsync(new SystemSettings(), new UserCredentials(
                'adm',
                'admpa$$'
            ));
        }));
    }
}
