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
use Prooph\EventStore\StreamAcl;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\UserCredentials;

class overriden_user_stream_security extends AuthenticationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $settings = new SystemSettings(
            new StreamAcl(['user1'], ['user1'], ['user1'], ['user1'], ['user1']),
            null
        );

        $this->connection->setSystemSettings($settings, new UserCredentials('adm', 'admpa$$'));
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function operations_on_user_stream_succeeds_for_authorized_user(): void
    {
        $stream = 'user-authorized-user';

        $this->readEvent($stream, 'user1', 'pa$$1');
        $this->ReadStreamForward($stream, 'user1', 'pa$$1');
        $this->ReadStreamBackward($stream, 'user1', 'pa$$1');

        $this->writeStream($stream, 'user1', 'pa$$1');
        $this->transStart($stream, 'user1', 'pa$$1');

        $transId = ($this->transStart($stream, 'adm', 'admpa$$'))->transactionId();
        $trans = $this->connection->continueTransaction($transId, new UserCredentials('user1', 'pa$$1'));

        $trans->write();
        $trans->commit();

        $this->readMeta($stream, 'user1', 'pa$$1');
        $this->writeMeta($stream, 'user1', 'pa$$1', null);

        $this->subscribeToStream($stream, 'user1', 'pa$$1');

        $this->deleteStream($stream, 'user1', 'pa$$1');
    }

    /** @test */
    public function operations_on_user_stream_fail_for_not_authorized_user(): void
    {
        $stream = 'user-not-authorized';

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent($stream, 'user2', 'pa$$2'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->ReadStreamForward($stream, 'user2', 'pa$$2'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->ReadStreamBackward($stream, 'user2', 'pa$$2'));

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream($stream, 'user2', 'pa$$2'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->transStart($stream, 'user2', 'pa$$2'));

        $transId = ($this->transStart($stream, 'adm', 'admpa$$'))->transactionId();
        $trans = $this->connection->continueTransaction($transId, new UserCredentials('user2', 'pa$$2'));

        $trans->write();
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $trans->commit());

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readMeta($stream, 'user2', 'pa$$2'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeMeta($stream, 'user2', 'pa$$2', null));

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->subscribeToStream($stream, 'user2', 'pa$$2'));

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->deleteStream($stream, 'user2', 'pa$$2'));
    }

    /** @test */
    public function operations_on_user_stream_fail_for_anonymous_user(): void
    {
        $stream = 'user-anonymous-user';

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent($stream, null, null));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->ReadStreamForward($stream, null, null));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->ReadStreamBackward($stream, null, null));

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream($stream, null, null));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->transStart($stream, null, null));

        $transId = ($this->transStart($stream, 'adm', 'admpa$$'))->transactionId();
        $trans = $this->connection->continueTransaction($transId, null);

        $trans->write();
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $trans->commit());

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readMeta($stream, null, null));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeMeta($stream, null, null, null));

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->subscribeToStream($stream, null, null));

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->deleteStream($stream, null, null));
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function operations_on_user_stream_succeed_for_admin(): void
    {
        $stream = 'user-admin';

        $this->readEvent($stream, 'adm', 'admpa$$');
        $this->ReadStreamForward($stream, 'adm', 'admpa$$');
        $this->ReadStreamBackward($stream, 'adm', 'admpa$$');

        $this->writeStream($stream, 'adm', 'admpa$$');
        $this->transStart($stream, 'adm', 'admpa$$');

        $transId = ($this->transStart($stream, 'adm', 'admpa$$'))->transactionId();
        $trans = $this->connection->continueTransaction($transId, new UserCredentials('adm', 'admpa$$'));

        $trans->write();
        $trans->commit();

        $this->readMeta($stream, 'adm', 'admpa$$');
        $this->writeMeta($stream, 'adm', 'admpa$$', null);

        $this->subscribeToStream($stream, 'adm', 'admpa$$');

        $this->deleteStream($stream, 'adm', 'admpa$$');
    }
}
