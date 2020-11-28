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
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\StreamAcl;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\UserCredentials;

class overriden_system_stream_security_for_all extends AuthenticationTestCase
{
    protected function setUpAsync(): Generator
    {
        yield from parent::setUpAsync();

        $settings = new SystemSettings(
            null,
            new StreamAcl([SystemRoles::ALL], [SystemRoles::ALL], [SystemRoles::ALL], [SystemRoles::ALL], [SystemRoles::ALL])
        );

        yield $this->connection->setSystemSettingsAsync($settings, new UserCredentials('adm', 'admpa$$'));
    }

    /** @test */
    public function operations_on_system_stream_succeeds_for_user(): Generator
    {
        yield $this->expectNoExceptionFromCallback(fn () => call(function (): Generator {
            $stream = '$sys-authorized-user2';

            yield $this->readEvent($stream, 'user1', 'pa$$1');
            yield $this->readStreamForward($stream, 'user1', 'pa$$1');
            yield $this->readStreamBackward($stream, 'user1', 'pa$$1');

            yield $this->writeStream($stream, 'user1', 'pa$$1');
            yield $this->transStart($stream, 'user1', 'pa$$1');

            $transId = (yield $this->transStart($stream, 'adm', 'admpa$$'))->transactionId();
            $trans = $this->connection->continueTransaction($transId, new UserCredentials('user1', 'pa$$1'));

            \assert($trans instanceof EventStoreTransaction);
            yield $trans->writeAsync();
            yield $trans->commitAsync();

            yield $this->readMeta($stream, 'user1', 'pa$$1');
            yield $this->writeMeta($stream, 'user1', 'pa$$1', null);

            (yield $this->subscribeToStream($stream, 'user1', 'pa$$1'))->unsubscribe();

            yield $this->deleteStream($stream, 'user1', 'pa$$1');
        }));
    }

    /** @test */
    public function operations_on_system_stream_fail_for_anonymous_user(): Generator
    {
        yield $this->expectNoExceptionFromCallback(fn () => call(function (): Generator {
            $stream = '$sys-anonymous-user2';

            yield $this->readEvent($stream, null, null);
            yield $this->readStreamForward($stream, null, null);
            yield $this->readStreamBackward($stream, null, null);

            yield $this->writeStream($stream, null, null);
            yield $this->transStart($stream, null, null);

            $transId = (yield $this->transStart($stream, 'adm', 'admpa$$'))->transactionId();
            $trans = $this->connection->continueTransaction($transId, new UserCredentials('user2', 'pa$$2'));

            \assert($trans instanceof EventStoreTransaction);
            yield $trans->writeAsync();
            yield $trans->commitAsync();

            yield $this->readMeta($stream, null, null);
            yield $this->writeMeta($stream, null, null, null);

            (yield $this->subscribeToStream($stream, null, null))->unsubscribe();

            yield $this->deleteStream($stream, null, null);
        }));
    }

    /** @test */
    public function operations_on_system_stream_succeed_for_admin(): Generator
    {
        yield $this->expectNoExceptionFromCallback(fn () => call(function (): Generator {
            $stream = '$sys-admin2';

            yield $this->readEvent($stream, 'adm', 'admpa$$');
            yield $this->readStreamForward($stream, 'adm', 'admpa$$');
            yield $this->readStreamBackward($stream, 'adm', 'admpa$$');

            yield $this->writeStream($stream, 'adm', 'admpa$$');
            yield $this->transStart($stream, 'adm', 'admpa$$');

            $transId = (yield $this->transStart($stream, 'adm', 'admpa$$'))->transactionId();
            $trans = $this->connection->continueTransaction($transId, new UserCredentials('adm', 'admpa$$'));

            \assert($trans instanceof EventStoreTransaction);
            yield $trans->writeAsync();
            yield $trans->commitAsync();

            yield $this->readMeta($stream, 'adm', 'admpa$$');
            yield $this->writeMeta($stream, 'adm', 'admpa$$', null);

            (yield $this->subscribeToStream($stream, 'adm', 'admpa$$'))->unsubscribe();

            yield $this->deleteStream($stream, 'adm', 'admpa$$');
        }));
    }
}
