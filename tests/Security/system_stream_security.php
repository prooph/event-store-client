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
use Prooph\EventStore\Async\EventStoreTransaction;
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\UserCredentials;
use Throwable;

class system_stream_security extends AuthenticationTestCase
{
    /**
     * @test
     * @throws Throwable
     */
    public function operations_on_system_stream_with_no_acl_set_fail_for_non_admin(): void
    {
        wait(call(function () {
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('$system-no-acl', 'user1', 'pa$$1'));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamForward('$system-no-acl', 'user1', 'pa$$1'));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamBackward('$system-no-acl', 'user1', 'pa$$1'));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$system-no-acl', 'user1', 'pa$$1'));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->transStart('$system-no-acl', 'user1', 'pa$$1'));

            $transaction = yield $this->transStart('$system-no-acl', 'adm', 'admpa$$');
            \assert($transaction instanceof EventStoreTransaction);
            $id = $transaction->transactionId();
            $trans = $this->connection->continueTransaction($id, new UserCredentials('user1', 'pa$$1'));
            yield $trans->writeAsync();
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $trans->commitAsync());

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readMeta('$system-no-acl', 'user1', 'pa$$1'));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeMeta('$system-no-acl', 'user1', 'pa$$1', null));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->subscribeToStream('$system-no-acl', 'user1', 'pa$$1'));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function operations_on_system_stream_with_no_acl_set_succeed_for_admin(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => call(function () {
                yield $this->readEvent('$system-no-acl', 'adm', 'admpa$$');
                yield $this->readStreamForward('$system-no-acl', 'adm', 'admpa$$');
                yield $this->readStreamBackward('$system-no-acl', 'adm', 'admpa$$');

                yield $this->writeStream('$system-no-acl', 'adm', 'admpa$$');
                yield $this->transStart('$system-no-acl', 'adm', 'admpa$$');

                $transaction = yield $this->transStart('$system-no-acl', 'adm', 'admpa$$');
                \assert($transaction instanceof EventStoreTransaction);
                $id = $transaction->transactionId();
                $trans = $this->connection->continueTransaction($id, new UserCredentials('adm', 'admpa$$'));
                yield $trans->writeAsync();
                yield $trans->commitAsync();

                yield $this->readMeta('$system-no-acl', 'adm', 'admpa$$');
                yield $this->writeMeta('$system-no-acl', 'adm', 'admpa$$', null);

                yield $this->subscribeToStream('$system-no-acl', 'adm', 'admpa$$');
            }));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function operations_on_system_stream_with_acl_set_to_usual_user_fail_for_not_authorized_user(): void
    {
        wait(call(function () {
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('$system-no-acl', 'user2', 'pa$$2'));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamForward('$system-no-acl', 'user2', 'pa$$2'));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamBackward('$system-no-acl', 'user2', 'pa$$2'));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$system-no-acl', 'user2', 'pa$$2'));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->transStart('$system-no-acl', 'user2', 'pa$$2'));

            $transaction = yield $this->transStart('$system-acl', 'user1', 'pa$$1');
            \assert($transaction instanceof EventStoreTransaction);
            $id = $transaction->transactionId();
            $trans = $this->connection->continueTransaction($id, new UserCredentials('user2', 'pa$$2'));
            yield $trans->writeAsync();
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $trans->commitAsync());

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readMeta('$system-no-acl', 'user2', 'pa$$2'));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeMeta('$system-no-acl', 'user2', 'pa$$2', null));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->subscribeToStream('$system-no-acl', 'user2', 'pa$$2'));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function operations_on_system_stream_with_acl_set_to_usual_user_succeed_for_that_user(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => call(function () {
                yield $this->readEvent('$system-acl', 'user1', 'pa$$1');
                yield $this->readStreamForward('$system-acl', 'user1', 'pa$$1');
                yield $this->readStreamBackward('$system-acl', 'user1', 'pa$$1');

                yield $this->writeStream('$system-acl', 'user1', 'pa$$1');
                yield $this->transStart('$system-acl', 'user1', 'pa$$1');

                $transaction = yield $this->transStart('$system-acl', 'adm', 'admpa$$');
                \assert($transaction instanceof EventStoreTransaction);
                $id = $transaction->transactionId();
                $trans = $this->connection->continueTransaction($id, new UserCredentials('user1', 'pa$$1'));
                yield $trans->writeAsync();
                yield $trans->commitAsync();

                yield $this->readMeta('$system-acl', 'user1', 'pa$$1');
                yield $this->writeMeta('$system-acl', 'user1', 'pa$$1', 'user1');

                yield $this->subscribeToStream('$system-acl', 'user1', 'pa$$1');
            }));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function operations_on_system_stream_with_acl_set_to_usual_user_succeed_for_admin(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => call(function () {
                yield $this->readEvent('$system-acl', 'adm', 'admpa$$');
                yield $this->readStreamForward('$system-acl', 'adm', 'admpa$$');
                yield $this->readStreamBackward('$system-acl', 'adm', 'admpa$$');

                yield $this->writeStream('$system-acl', 'adm', 'admpa$$');
                yield $this->transStart('$system-acl', 'adm', 'admpa$$');

                $transaction = yield $this->transStart('$system-acl', 'adm', 'admpa$$');
                \assert($transaction instanceof EventStoreTransaction);
                $id = $transaction->transactionId();
                $trans = $this->connection->continueTransaction($id, new UserCredentials('adm', 'admpa$$'));
                yield $trans->writeAsync();
                yield $trans->commitAsync();

                yield $this->readMeta('$system-acl', 'adm', 'admpa$$');
                yield $this->writeMeta('$system-acl', 'adm', 'admpa$$', null);

                yield $this->subscribeToStream('$system-acl', 'adm', 'admpa$$');
            }));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function operations_on_system_stream_with_acl_set_to_admins_fail_for_usual_user(): void
    {
        wait(call(function () {
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('$system-adm', 'user1', 'pa$$1'));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamForward('$system-adm', 'user1', 'pa$$1'));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamBackward('$system-adm', 'user1', 'pa$$1'));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('$system-adm', 'user1', 'pa$$1'));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->transStart('$system-adm', 'user1', 'pa$$1'));

            $transaction = yield $this->transStart('$system-adm', 'adm', 'admpa$$');
            \assert($transaction instanceof EventStoreTransaction);
            $id = $transaction->transactionId();
            $trans = $this->connection->continueTransaction($id, new UserCredentials('user1', 'pa$$1'));
            yield $trans->writeAsync();
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $trans->commitAsync());

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readMeta('$system-adm', 'user1', 'pa$$1'));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeMeta('$system-adm', 'user1', 'pa$$1', null));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->subscribeToStream('$system-adm', 'user1', 'pa$$1'));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function operations_on_system_stream_with_acl_set_to_admins_succeed_for_admin(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => call(function () {
                yield $this->readEvent('$system-adm', 'adm', 'admpa$$');
                yield $this->readStreamForward('$system-adm', 'adm', 'admpa$$');
                yield $this->readStreamBackward('$system-adm', 'adm', 'admpa$$');

                yield $this->writeStream('$system-adm', 'adm', 'admpa$$');
                yield $this->transStart('$system-adm', 'adm', 'admpa$$');

                $transaction = yield $this->transStart('$system-adm', 'adm', 'admpa$$');
                \assert($transaction instanceof EventStoreTransaction);
                $id = $transaction->transactionId();
                $trans = $this->connection->continueTransaction($id, new UserCredentials('adm', 'admpa$$'));
                yield $trans->writeAsync();
                yield $trans->commitAsync();

                yield $this->readMeta('$system-adm', 'adm', 'admpa$$');
                yield $this->writeMeta('$system-adm', 'adm', 'admpa$$', null);

                yield $this->subscribeToStream('$system-adm', 'adm', 'admpa$$');
            }));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function operations_on_system_stream_with_acl_set_to_all_succeed_for_not_authenticated_user(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => call(function () {
                yield $this->readEvent('$system-all', null, null);
                yield $this->readStreamForward('$system-all', null, null);
                yield $this->readStreamBackward('$system-all', null, null);

                yield $this->writeStream('$system-all', null, null);
                yield $this->transStart('$system-all', null, null);

                $transaction = yield $this->transStart('$system-all', null, null);
                \assert($transaction instanceof EventStoreTransaction);
                $id = $transaction->transactionId();
                $trans = $this->connection->continueTransaction($id);
                yield $trans->writeAsync();
                yield $trans->commitAsync();

                yield $this->readMeta('$system-all', null, null);
                yield $this->writeMeta('$system-all', null, null, SystemRoles::ALL);

                yield $this->subscribeToStream('$system-all', null, null);
            }));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function operations_on_system_stream_with_acl_set_to_all_succeed_for_usual_user(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => call(function () {
                yield $this->readEvent('$system-all', 'user1', 'pa$$1');
                yield $this->readStreamForward('$system-all', 'user1', 'pa$$1');
                yield $this->readStreamBackward('$system-all', 'user1', 'pa$$1');

                yield $this->writeStream('$system-all', 'user1', 'pa$$1');
                yield $this->transStart('$system-all', 'user1', 'pa$$1');

                $transaction = yield $this->transStart('$system-all', 'user1', 'pa$$1');
                \assert($transaction instanceof EventStoreTransaction);
                $id = $transaction->transactionId();
                $trans = $this->connection->continueTransaction($id);
                yield $trans->writeAsync();
                yield $trans->commitAsync();

                yield $this->readMeta('$system-all', 'user1', 'pa$$1');
                yield $this->writeMeta('$system-all', 'user1', 'pa$$1', SystemRoles::ALL);

                yield $this->subscribeToStream('$system-all', 'user1', 'pa$$1');
            }));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function operations_on_system_stream_with_acl_set_to_all_succeed_for_admin(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => call(function () {
                yield $this->readEvent('$system-all', 'adm', 'admpa$$');
                yield $this->readStreamForward('$system-all', 'adm', 'admpa$$');
                yield $this->readStreamBackward('$system-all', 'adm', 'admpa$$');

                yield $this->writeStream('$system-all', 'adm', 'admpa$$');
                yield $this->transStart('$system-all', 'adm', 'admpa$$');

                $transaction = yield $this->transStart('$system-all', 'adm', 'admpa$$');
                \assert($transaction instanceof EventStoreTransaction);
                $id = $transaction->transactionId();
                $trans = $this->connection->continueTransaction($id);
                yield $trans->writeAsync();
                yield $trans->commitAsync();

                yield $this->readMeta('$system-all', 'adm', 'admpa$$');
                yield $this->writeMeta('$system-all', 'adm', 'admpa$$', SystemRoles::ALL);

                yield $this->subscribeToStream('$system-all', 'adm', 'admpa$$');
            }));
        }));
    }
}
