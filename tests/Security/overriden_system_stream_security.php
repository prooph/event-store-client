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
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\StreamAcl;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\UserCredentials;
use Throwable;

class overriden_system_stream_security extends AuthenticationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        wait(call(function () {
            $settings = new SystemSettings(
                null,
                new StreamAcl(['user1'], ['user1'], ['user1'], ['user1'], ['user1'])
            );
            yield $this->connection->setSystemSettingsAsync($settings, new UserCredentials('adm', 'admpa$$'));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function operations_on_system_stream_succeed_for_authorized_user(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => call(function () {
                $stream = '$sys-authorized-user';

                yield $this->readEvent($stream, 'user1', 'pa$$1');
                yield $this->ReadStreamForward($stream, 'user1', 'pa$$1');
                yield $this->ReadStreamBackward($stream, 'user1', 'pa$$1');

                yield $this->writeStream($stream, 'user1', 'pa$$1');
                yield $this->transStart($stream, 'user1', 'pa$$1');

                $transId = (yield $this->transStart($stream, 'adm', 'admpa$$'))->transactionId();
                $trans = $this->connection->continueTransaction($transId, new UserCredentials('user1', 'pa$$1'));

                \assert($trans instanceof EventStoreTransaction);
                yield $trans->writeAsync();
                yield $trans->commitAsync();

                yield $this->readMeta($stream, 'user1', 'pa$$1');
                yield $this->writeMeta($stream, 'user1', 'pa$$1', null);

                yield $this->subscribeToStream($stream, 'user1', 'pa$$1');

                yield $this->deleteStream($stream, 'user1', 'pa$$1');
            }));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function operations_on_system_stream_fail_for_not_authorized_user(): void
    {
        wait(call(function () {
            $stream = '$sys-not-authorized-user';

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent($stream, 'user2', 'pa$$2'));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->ReadStreamForward($stream, 'user2', 'pa$$2'));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->ReadStreamBackward($stream, 'user2', 'pa$$2'));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream($stream, 'user2', 'pa$$2'));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->transStart($stream, 'user2', 'pa$$2'));

            $transId = (yield $this->transStart($stream, 'adm', 'admpa$$'))->transactionId();
            $trans = $this->connection->continueTransaction($transId, new UserCredentials('user2', 'pa$$2'));

            \assert($trans instanceof EventStoreTransaction);
            yield $trans->writeAsync();
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $trans->commitAsync());

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readMeta($stream, 'user2', 'pa$$2'));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeMeta($stream, 'user2', 'pa$$2', null));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->subscribeToStream($stream, 'user2', 'pa$$2'));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->deleteStream($stream, 'user2', 'pa$$2'));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function operations_on_system_stream_fail_for_anonymous_user(): void
    {
        wait(call(function () {
            $stream = '$sys-anonymous-user';

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent($stream, null, null));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->ReadStreamForward($stream, null, null));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->ReadStreamBackward($stream, null, null));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream($stream, null, null));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->transStart($stream, null, null));

            $transId = (yield $this->transStart($stream, 'adm', 'admpa$$'))->transactionId();
            $trans = $this->connection->continueTransaction($transId, null);

            \assert($trans instanceof EventStoreTransaction);
            yield $trans->writeAsync();
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $trans->commitAsync());

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readMeta($stream, null, null));
            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeMeta($stream, null, null, null));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->subscribeToStream($stream, null, null));

            yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->deleteStream($stream, null, null));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function operations_on_system_stream_succeed_for_admin(): void
    {
        wait(call(function () {
            yield $this->expectNoExceptionFromCallback(fn () => call(function () {
                $stream = '$sys-admin';

                yield $this->readEvent($stream, 'adm', 'admpa$$');
                yield $this->ReadStreamForward($stream, 'adm', 'admpa$$');
                yield $this->ReadStreamBackward($stream, 'adm', 'admpa$$');

                yield $this->writeStream($stream, 'adm', 'admpa$$');
                yield $this->transStart($stream, 'adm', 'admpa$$');

                $transId = (yield $this->transStart($stream, 'adm', 'admpa$$'))->transactionId();
                $trans = $this->connection->continueTransaction($transId, new UserCredentials('adm', 'admpa$$'));

                \assert($trans instanceof EventStoreTransaction);
                yield $trans->writeAsync();
                yield $trans->commitAsync();

                yield $this->readMeta($stream, 'adm', 'admpa$$');
                yield $this->writeMeta($stream, 'adm', 'admpa$$', null);

                yield $this->subscribeToStream($stream, 'adm', 'admpa$$');

                yield $this->deleteStream($stream, 'adm', 'admpa$$');
            }));
        }));
    }
}
