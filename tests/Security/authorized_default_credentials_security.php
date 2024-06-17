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
use Prooph\EventStore\Exception\NotAuthenticated;
use Prooph\EventStore\UserCredentials;

class authorized_default_credentials_security extends AuthenticationTestCase
{
    protected function setUp(): void
    {
        $this->userCredentials = new UserCredentials('user1', 'pa$$1');

        parent::setUp();
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function all_operations_succeeds_when_passing_no_explicit_credentials(): void
    {
        $this->readAllForward(null, null);
        $this->readAllBackward(null, null);

        $this->readEvent('read-stream', null, null);
        $this->readStreamForward('read-stream', null, null);
        $this->readStreamBackward('read-stream', null, null);

        $this->writeStream('write-stream', null, null);

        $trans = $this->transStart('write-stream', null, null);
        $trans->write();
        $trans->commit();

        $this->readMeta('metaread-stream', null, null);
        $this->writeMeta('metawrite-stream', null, null, 'user1');

        $this->subscribeToStream('read-stream', null, null);
        $this->subscribeToAll(null, null);
    }

    /** @test */
    public function all_operations_are_not_authenticated_when_overriden_with_not_existing_credentials(): void
    {
        $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readAllForward('badlogin', 'badpass'));
        $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readAllBackward('badlogin', 'badpass'));

        $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readEvent('read-stream', 'badlogin', 'badpass'));
        $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readStreamForward('read-stream', 'badlogin', 'badpass'));
        $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readStreamBackward('read-stream', 'badlogin', 'badpass'));

        $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->writeStream('write-stream', 'badlogin', 'badpass'));

        $trans = $this->transStart('write-stream', null, null);
        $transId = $trans->transactionId();

        $trans = $this->connection->continueTransaction($transId, new UserCredentials('badlogin', 'badpass'));
        $this->expectExceptionFromCallback(
            NotAuthenticated::class,
            function () use ($trans): void {
                $trans->write();
                $trans->commit();
            }
        );

        $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readMeta('metaread-stream', 'badlogin', 'badpass'));
        $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->writeMeta('metawrite-stream', 'badlogin', 'badpass', 'user1'));

        $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->subscribeToStream('read-stream', 'badlogin', 'badpass'));
        $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->subscribeToAll('badlogin', 'badpass'));
    }

    /** @test */
    public function all_operations_are_not_authorized_when_overriden_with_not_authorized_credentials(): void
    {
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readAllForward('user2', 'pa$$2'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readAllBackward('user2', 'pa$$2'));

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('read-stream', 'user2', 'pa$$2'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamForward('read-stream', 'user2', 'pa$$2'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamBackward('read-stream', 'user2', 'pa$$2'));

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('write-stream', 'user2', 'pa$$2'));

        $trans = $this->transStart('write-stream', null, null);
        $transId = $trans->transactionId();
        $trans = $this->connection->continueTransaction($transId, new UserCredentials('user2', 'pa$$2'));
        $trans->write();
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $trans->commit());

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readMeta('metaread-stream', 'user2', 'pa$$2'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeMeta('metawrite-stream', 'user2', 'pa$$2', 'user1'));

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->subscribeToStream('read-stream', 'user2', 'pa$$2'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->subscribeToAll('user2', 'pa$$2'));
    }
}
