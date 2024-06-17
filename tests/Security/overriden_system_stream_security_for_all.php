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
use Prooph\EventStore\StreamAcl;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\UserCredentials;

class overriden_system_stream_security_for_all extends AuthenticationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $settings = new SystemSettings(
            null,
            new StreamAcl([SystemRoles::All], [SystemRoles::All], [SystemRoles::All], [SystemRoles::All], [SystemRoles::All])
        );

        $this->connection->setSystemSettings($settings, new UserCredentials('adm', 'admpa$$'));
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function operations_on_system_stream_succeeds_for_user(): void
    {
        $stream = '$sys-authorized-user2';

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

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function operations_on_system_stream_fail_for_anonymous_user(): void
    {
        $stream = '$sys-anonymous-user2';

        $this->readEvent($stream, null, null);
        $this->ReadStreamForward($stream, null, null);
        $this->ReadStreamBackward($stream, null, null);

        $this->writeStream($stream, null, null);
        $this->transStart($stream, null, null);

        $transId = ($this->transStart($stream, 'adm', 'admpa$$'))->transactionId();
        $trans = $this->connection->continueTransaction($transId, new UserCredentials('user2', 'pa$$2'));

        $trans->write();
        $trans->commit();

        $this->readMeta($stream, null, null);
        $this->writeMeta($stream, null, null, null);

        $this->subscribeToStream($stream, null, null);

        $this->deleteStream($stream, null, null);
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function operations_on_system_stream_succeed_for_admin(): void
    {
        $stream = '$sys-admin2';

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
