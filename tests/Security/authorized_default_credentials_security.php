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
use Generator;
use Prooph\EventStore\Async\EventStoreTransaction;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\NotAuthenticated;
use Prooph\EventStore\UserCredentials;

class authorized_default_credentials_security extends AuthenticationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->userCredentials = new UserCredentials('user1', 'pa$$1');
    }

    /** @test */
    public function all_operations_succeeds_when_passing_no_explicit_credentials(): Generator
    {
        yield $this->expectNoExceptionFromCallback(fn () => call(function (): Generator {
            yield $this->readAllForward(null, null);
            yield $this->readAllBackward(null, null);

            yield $this->readEvent('read-stream', null, null);
            yield $this->readStreamForward('read-stream', null, null);
            yield $this->readStreamBackward('read-stream', null, null);

            yield $this->writeStream('write-stream', null, null);

            $trans = yield $this->transStart('write-stream', null, null);
            \assert($trans instanceof EventStoreTransaction);
            yield $trans->writeAsync();
            yield $trans->commitAsync();

            yield $this->readMeta('metaread-stream', null, null);
            yield $this->writeMeta('metawrite-stream', null, null, 'user1');

            (yield $this->subscribeToStream('read-stream', null, null))->unsubscribe();
            (yield $this->subscribeToAll(null, null))->unsubscribe();
        }));
    }

    /** @test */
    public function all_operations_are_not_authenticated_when_overriden_with_not_existing_credentials(): Generator
    {
        yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readAllForward('badlogin', 'badpass'));
        yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readAllBackward('badlogin', 'badpass'));

        yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readEvent('read-stream', 'badlogin', 'badpass'));
        yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readStreamForward('read-stream', 'badlogin', 'badpass'));
        yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readStreamBackward('read-stream', 'badlogin', 'badpass'));

        yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->writeStream('write-stream', 'badlogin', 'badpass'));

        $trans = yield $this->transStart('write-stream', null, null);
        \assert($trans instanceof EventStoreTransaction);
        $transId = $trans->transactionId();

        $trans = $this->connection->continueTransaction($transId, new UserCredentials('badlogin', 'badpass'));
        yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => call(function () use ($trans): Generator {
            yield $trans->writeAsync();
            yield $trans->commitAsync();
        }));

        yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->readMeta('metaread-stream', 'badlogin', 'badpass'));
        yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->writeMeta('metawrite-stream', 'badlogin', 'badpass', 'user1'));

        yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->subscribeToStream('read-stream', 'badlogin', 'badpass'));
        yield $this->expectExceptionFromCallback(NotAuthenticated::class, fn () => $this->subscribeToAll('badlogin', 'badpass'));
    }

    /** @test */
    public function all_operations_are_not_authorized_when_overriden_with_not_authorized_credentials(): Generator
    {
        yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readAllForward('user2', 'pa$$2'));
        yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readAllBackward('user2', 'pa$$2'));

        yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readEvent('read-stream', 'user2', 'pa$$2'));
        yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamForward('read-stream', 'user2', 'pa$$2'));
        yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readStreamBackward('read-stream', 'user2', 'pa$$2'));

        yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeStream('write-stream', 'user2', 'pa$$2'));

        $trans = yield $this->transStart('write-stream', null, null);
        \assert($trans instanceof EventStoreTransaction);
        $transId = $trans->transactionId();
        $trans = $this->connection->continueTransaction($transId, new UserCredentials('user2', 'pa$$2'));
        yield $trans->writeAsync();
        yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $trans->commitAsync());

        yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->readMeta('metaread-stream', 'user2', 'pa$$2'));
        yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->writeMeta('metawrite-stream', 'user2', 'pa$$2', 'user1'));

        yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->subscribeToStream('read-stream', 'user2', 'pa$$2'));
        yield $this->expectExceptionFromCallback(AccessDenied::class, fn () => $this->subscribeToAll('user2', 'pa$$2'));
    }
}
