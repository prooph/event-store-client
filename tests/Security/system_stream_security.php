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
use Prooph\EventStore\UserCredentials;

class system_stream_security extends AuthenticationTestCase
{
    /** @test */
    public function operations_on_system_stream_with_no_acl_set_fail_for_non_admin(): void
    {
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('$system-no-acl', 'user1', 'pa$$1'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamForward('$system-no-acl', 'user1', 'pa$$1'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamBackward('$system-no-acl', 'user1', 'pa$$1'));

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$system-no-acl', 'user1', 'pa$$1'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->transStart('$system-no-acl', 'user1', 'pa$$1'));

        $transaction = $this->transStart('$system-no-acl', 'adm', 'admpa$$');
        $id = $transaction->transactionId();
        $trans = $this->connection->continueTransaction($id, new UserCredentials('user1', 'pa$$1'));
        $trans->write();
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $trans->commit());

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readMeta('$system-no-acl', 'user1', 'pa$$1'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeMeta('$system-no-acl', 'user1', 'pa$$1', null));

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->subscribeToStream('$system-no-acl', 'user1', 'pa$$1'));
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function operations_on_system_stream_with_no_acl_set_succeed_for_admin(): void
    {
        $this->readEvent('$system-no-acl', 'adm', 'admpa$$');
        $this->readStreamForward('$system-no-acl', 'adm', 'admpa$$');
        $this->readStreamBackward('$system-no-acl', 'adm', 'admpa$$');

        $this->writeStream('$system-no-acl', 'adm', 'admpa$$');
        $this->transStart('$system-no-acl', 'adm', 'admpa$$');

        $transaction = $this->transStart('$system-no-acl', 'adm', 'admpa$$');
        $id = $transaction->transactionId();
        $trans = $this->connection->continueTransaction($id, new UserCredentials('adm', 'admpa$$'));
        $trans->write();
        $trans->commit();

        $this->readMeta('$system-no-acl', 'adm', 'admpa$$');
        $this->writeMeta('$system-no-acl', 'adm', 'admpa$$', null);

        $this->subscribeToStream('$system-no-acl', 'adm', 'admpa$$');
    }

    /** @test */
    public function operations_on_system_stream_with_acl_set_to_usual_user_fail_for_not_authorized_user(): void
    {
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('$system-no-acl', 'user2', 'pa$$2'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamForward('$system-no-acl', 'user2', 'pa$$2'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamBackward('$system-no-acl', 'user2', 'pa$$2'));

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$system-no-acl', 'user2', 'pa$$2'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->transStart('$system-no-acl', 'user2', 'pa$$2'));

        $transaction = $this->transStart('$system-acl', 'user1', 'pa$$1');
        $id = $transaction->transactionId();
        $trans = $this->connection->continueTransaction($id, new UserCredentials('user2', 'pa$$2'));
        $trans->write();
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $trans->commit());

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readMeta('$system-no-acl', 'user2', 'pa$$2'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeMeta('$system-no-acl', 'user2', 'pa$$2', null));

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->subscribeToStream('$system-no-acl', 'user2', 'pa$$2'));
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function operations_on_system_stream_with_acl_set_to_usual_user_succeed_for_that_user(): void
    {
        $this->readEvent('$system-acl', 'user1', 'pa$$1');
        $this->readStreamForward('$system-acl', 'user1', 'pa$$1');
        $this->readStreamBackward('$system-acl', 'user1', 'pa$$1');

        $this->writeStream('$system-acl', 'user1', 'pa$$1');
        $this->transStart('$system-acl', 'user1', 'pa$$1');

        $transaction = $this->transStart('$system-acl', 'adm', 'admpa$$');
        $id = $transaction->transactionId();
        $trans = $this->connection->continueTransaction($id, new UserCredentials('user1', 'pa$$1'));
        $trans->write();
        $trans->commit();

        $this->readMeta('$system-acl', 'user1', 'pa$$1');
        $this->writeMeta('$system-acl', 'user1', 'pa$$1', 'user1');

        $this->subscribeToStream('$system-acl', 'user1', 'pa$$1');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function operations_on_system_stream_with_acl_set_to_usual_user_succeed_for_admin(): void
    {
        $this->readEvent('$system-acl', 'adm', 'admpa$$');
        $this->readStreamForward('$system-acl', 'adm', 'admpa$$');
        $this->readStreamBackward('$system-acl', 'adm', 'admpa$$');

        $this->writeStream('$system-acl', 'adm', 'admpa$$');
        $this->transStart('$system-acl', 'adm', 'admpa$$');

        $transaction = $this->transStart('$system-acl', 'adm', 'admpa$$');
        $id = $transaction->transactionId();
        $trans = $this->connection->continueTransaction($id, new UserCredentials('adm', 'admpa$$'));
        $trans->write();
        $trans->commit();

        $this->readMeta('$system-acl', 'adm', 'admpa$$');
        $this->writeMeta('$system-acl', 'adm', 'admpa$$', null);

        $this->subscribeToStream('$system-acl', 'adm', 'admpa$$');
    }

    /** @test */
    public function operations_on_system_stream_with_acl_set_to_admins_fail_for_usual_user(): void
    {
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('$system-adm', 'user1', 'pa$$1'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamForward('$system-adm', 'user1', 'pa$$1'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamBackward('$system-adm', 'user1', 'pa$$1'));

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$system-adm', 'user1', 'pa$$1'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->transStart('$system-adm', 'user1', 'pa$$1'));

        $transaction = $this->transStart('$system-adm', 'adm', 'admpa$$');
        $id = $transaction->transactionId();
        $trans = $this->connection->continueTransaction($id, new UserCredentials('user1', 'pa$$1'));
        $trans->write();
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $trans->commit());

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readMeta('$system-adm', 'user1', 'pa$$1'));
        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeMeta('$system-adm', 'user1', 'pa$$1', null));

        $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->subscribeToStream('$system-adm', 'user1', 'pa$$1'));
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function operations_on_system_stream_with_acl_set_to_admins_succeed_for_admin(): void
    {
        $this->readEvent('$system-adm', 'adm', 'admpa$$');
        $this->readStreamForward('$system-adm', 'adm', 'admpa$$');
        $this->readStreamBackward('$system-adm', 'adm', 'admpa$$');

        $this->writeStream('$system-adm', 'adm', 'admpa$$');
        $this->transStart('$system-adm', 'adm', 'admpa$$');

        $transaction = $this->transStart('$system-adm', 'adm', 'admpa$$');
        $id = $transaction->transactionId();
        $trans = $this->connection->continueTransaction($id, new UserCredentials('adm', 'admpa$$'));
        $trans->write();
        $trans->commit();

        $this->readMeta('$system-adm', 'adm', 'admpa$$');
        $this->writeMeta('$system-adm', 'adm', 'admpa$$', null);

        $this->subscribeToStream('$system-adm', 'adm', 'admpa$$');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function operations_on_system_stream_with_acl_set_to_all_succeed_for_not_authenticated_user(): void
    {
        $this->readEvent('$system-all', null, null);
        $this->readStreamForward('$system-all', null, null);
        $this->readStreamBackward('$system-all', null, null);

        $this->writeStream('$system-all', null, null);
        $this->transStart('$system-all', null, null);

        $transaction = $this->transStart('$system-all', null, null);
        $id = $transaction->transactionId();
        $trans = $this->connection->continueTransaction($id);
        $trans->write();
        $trans->commit();

        $this->readMeta('$system-all', null, null);
        $this->writeMeta('$system-all', null, null, SystemRoles::All);

        $this->subscribeToStream('$system-all', null, null);
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function operations_on_system_stream_with_acl_set_to_all_succeed_for_usual_user(): void
    {
        $this->readEvent('$system-all', 'user1', 'pa$$1');
        $this->readStreamForward('$system-all', 'user1', 'pa$$1');
        $this->readStreamBackward('$system-all', 'user1', 'pa$$1');

        $this->writeStream('$system-all', 'user1', 'pa$$1');
        $this->transStart('$system-all', 'user1', 'pa$$1');

        $transaction = $this->transStart('$system-all', 'user1', 'pa$$1');
        $id = $transaction->transactionId();
        $trans = $this->connection->continueTransaction($id);
        $trans->write();
        $trans->commit();

        $this->readMeta('$system-all', 'user1', 'pa$$1');
        $this->writeMeta('$system-all', 'user1', 'pa$$1', SystemRoles::All);

        $this->subscribeToStream('$system-all', 'user1', 'pa$$1');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function operations_on_system_stream_with_acl_set_to_all_succeed_for_admin(): void
    {
        $this->readEvent('$system-all', 'adm', 'admpa$$');
        $this->readStreamForward('$system-all', 'adm', 'admpa$$');
        $this->readStreamBackward('$system-all', 'adm', 'admpa$$');

        $this->writeStream('$system-all', 'adm', 'admpa$$');
        $this->transStart('$system-all', 'adm', 'admpa$$');

        $transaction = $this->transStart('$system-all', 'adm', 'admpa$$');
        $id = $transaction->transactionId();
        $trans = $this->connection->continueTransaction($id);
        $trans->write();
        $trans->commit();

        $this->readMeta('$system-all', 'adm', 'admpa$$');
        $this->writeMeta('$system-all', 'adm', 'admpa$$', SystemRoles::All);

        $this->subscribeToStream('$system-all', 'adm', 'admpa$$');
    }
}
