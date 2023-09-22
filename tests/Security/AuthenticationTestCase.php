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

use Amp\PHPUnit\AsyncTestCase;
use Closure;
use Prooph\EventStore\AllEventsSlice;
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\DeleteResult;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventReadResult;
use Prooph\EventStore\EventStoreConnection;
use Prooph\EventStore\EventStoreSubscription;
use Prooph\EventStore\EventStoreTransaction;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Position;
use Prooph\EventStore\RawStreamMetadataResult;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\WriteResult;
use Prooph\EventStoreClient\UserManagement\UsersManager;
use ProophTest\EventStoreClient\Helper\TestConnection;

abstract class AuthenticationTestCase extends AsyncTestCase
{
    protected ?EventStoreConnection $connection;

    protected ?UserCredentials $userCredentials = null;

    protected function setUp(): void
    {
        parent::setUp();

        $manager = new UsersManager(
            TestConnection::httpEndPoint(),
            5,
            false,
            false,
            $this->adminUser()
        );

        $manager->createUser(
            'user1',
            'Test User 1',
            [],
            'pa$$1'
        );

        $manager->createUser(
            'user2',
            'Test User 2',
            [],
            'pa$$2'
        );

        $manager->createUser(
            'adm',
            'Administrator User',
            [SystemRoles::Admins],
            'admpa$$'
        );

        $connection = TestConnection::create($this->adminUser());
        $connection->connect();

        $connection->setStreamMetadata(
            'noacl-stream',
            ExpectedVersion::Any,
            StreamMetadata::create()->build()
        );
        $connection->setStreamMetadata(
            'read-stream',
            ExpectedVersion::Any,
            StreamMetadata::create()->setReadRoles('user1')->build()
        );
        $connection->setStreamMetadata(
            'write-stream',
            ExpectedVersion::Any,
            StreamMetadata::create()->setWriteRoles('user1')->build()
        );
        $connection->setStreamMetadata(
            'metaread-stream',
            ExpectedVersion::Any,
            StreamMetadata::create()->setMetadataReadRoles('user1')->build()
        );
        $connection->setStreamMetadata(
            'metawrite-stream',
            ExpectedVersion::Any,
            StreamMetadata::create()->setMetadataWriteRoles('user1')->build()
        );

        $connection->setStreamMetadata(
            '$all',
            ExpectedVersion::Any,
            StreamMetadata::create()->setReadRoles('user1')->build(),
            new UserCredentials('adm', 'admpa$$')
        );

        $connection->setStreamMetadata(
            '$system-acl',
            ExpectedVersion::Any,
            StreamMetadata::create()
                ->setReadRoles('user1')
                ->setWriteRoles('user1')
                ->setMetadataReadRoles('user1')
                ->setMetadataWriteRoles('user1')
                ->build(),
            new UserCredentials('adm', 'admpa$$')
        );
        $connection->setStreamMetadata(
            '$system-adm',
            ExpectedVersion::Any,
            StreamMetadata::create()
                ->setReadRoles(SystemRoles::Admins)
                ->setWriteRoles(SystemRoles::Admins)
                ->setMetadataReadRoles(SystemRoles::Admins)
                ->setMetadataWriteRoles(SystemRoles::Admins)
                ->build(),
            new UserCredentials('adm', 'admpa$$')
        );

        $connection->setStreamMetadata(
            'normal-all',
            ExpectedVersion::Any,
            StreamMetadata::create()
                ->setReadRoles(SystemRoles::All)
                ->setWriteRoles(SystemRoles::All)
                ->setMetadataReadRoles(SystemRoles::All)
                ->setMetadataWriteRoles(SystemRoles::All)
                ->build()
        );
        $connection->setStreamMetadata(
            '$system-all',
            ExpectedVersion::Any,
            StreamMetadata::create()
                ->setReadRoles(SystemRoles::All)
                ->setWriteRoles(SystemRoles::All)
                ->setMetadataReadRoles(SystemRoles::All)
                ->setMetadataWriteRoles(SystemRoles::All)
                ->build(),
            new UserCredentials('adm', 'admpa$$')
        );

        $connection->close();

        $this->connection = TestConnection::create($this->userCredentials);

        $this->connection->connect();
    }

    protected function tearDown(): void
    {
        $this->userCredentials = null;
        $this->connection->close();

        $manager = new UsersManager(
            TestConnection::httpEndPoint(),
            5,
            false,
            false,
            $this->adminUser()
        );

        $manager->deleteUser('user1');
        $manager->deleteUser('user2');
        $manager->deleteUser('adm');

        $connection = TestConnection::create($this->adminUser());
        $connection->connect();

        $connection->setStreamMetadata(
            '$all',
            ExpectedVersion::Any,
            StreamMetadata::create()->build(),
            $this->adminUser()
        );

        $connection->setStreamMetadata(
            '$system-acl',
            ExpectedVersion::Any,
            StreamMetadata::create()->build(),
            $this->adminUser()
        );

        $connection->setSystemSettings(
            new SystemSettings(),
            $this->adminUser()
        );

        $connection->close();
    }

    protected function readEvent(string $streamId, ?string $login, ?string $password): EventReadResult
    {
        return $this->connection->readEvent(
            $streamId,
            -1,
            false,
            null === $login && null === $password
                ? null
                : new UserCredentials($login, $password)
        );
    }

    protected function readStreamForward(string $streamId, ?string $login, ?string $password): StreamEventsSlice
    {
        return $this->connection->readStreamEventsForward(
            $streamId,
            0,
            1,
            false,
            null === $login && null === $password
                ? null
                : new UserCredentials($login, $password)
        );
    }

    protected function readStreamBackward(string $streamId, ?string $login, ?string $password): StreamEventsSlice
    {
        return $this->connection->readStreamEventsBackward(
            $streamId,
            0,
            1,
            false,
            null === $login && null === $password
                ? null
                : new UserCredentials($login, $password)
        );
    }

    protected function writeStream(string $streamId, ?string $login, ?string $password): WriteResult
    {
        return $this->connection->appendToStream(
            $streamId,
            ExpectedVersion::Any,
            $this->createEvents(),
            null === $login && null === $password
                ? null
                : new UserCredentials($login, $password)
        );
    }

    protected function transStart(string $streamId, ?string $login, ?string $password): EventStoreTransaction
    {
        return $this->connection->startTransaction(
            $streamId,
            ExpectedVersion::Any,
            null === $login && null === $password
                ? null
                : new UserCredentials($login, $password)
        );
    }

    protected function readAllForward(?string $login, ?string $password): AllEventsSlice
    {
        return $this->connection->readAllEventsForward(
            Position::start(),
            1,
            false,
            null === $login && null === $password
                ? null
                : new UserCredentials($login, $password)
        );
    }

    protected function readAllBackward(?string $login, ?string $password): AllEventsSlice
    {
        return $this->connection->readAllEventsBackward(
            Position::end(),
            1,
            false,
            null === $login && null === $password
                ? null
                : new UserCredentials($login, $password)
        );
    }

    protected function readMeta(string $streamId, ?string $login, ?string $password): RawStreamMetadataResult
    {
        return $this->connection->getRawStreamMetadata(
            $streamId,
            null === $login && null === $password
                ? null
                : new UserCredentials($login, $password)
        );
    }

    protected function writeMeta(string $streamId, ?string $login, ?string $password, ?string $metawriteRole): WriteResult
    {
        return $this->connection->setStreamMetadata(
            $streamId,
            ExpectedVersion::Any,
            null === $metawriteRole
                ? StreamMetadata::create()
                    ->build()
                : StreamMetadata::create()
                    ->setReadRoles($metawriteRole)
                    ->setWriteRoles($metawriteRole)
                    ->setMetadataReadRoles($metawriteRole)
                    ->setMetadataWriteRoles($metawriteRole)
                    ->build(),
            null === $login && null === $password
                ? null
                : new UserCredentials($login, $password)
        );
    }

    protected function subscribeToStream(string $streamId, ?string $login, ?string $password): EventStoreSubscription
    {
        return $this->connection->subscribeToStream(
            $streamId,
            false,
            function (
                EventStoreSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ): void {
            },
            null,
            null === $login && null === $password
                ? null
                : new UserCredentials($login, $password)
        );
    }

    protected function subscribeToAll(?string $login, ?string $password): EventStoreSubscription
    {
        return $this->connection->subscribeToAll(
            false,
            function (
                EventStoreSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ): void {
            },
            null,
            null === $login && null === $password
                ? null
                : new UserCredentials($login, $password)
        );
    }

    protected function createStreamWithMeta(StreamMetadata $metadata, ?string $streamPrefix = null): string
    {
        $stream = ($streamPrefix ?? '') . $this->getName();

        $this->connection->setStreamMetadata(
            $stream,
            ExpectedVersion::NoStream,
            $metadata,
            new UserCredentials('adm', 'admpa$$')
        );

        return $stream;
    }

    protected function deleteStream(string $streamId, ?string $login, ?string $password): DeleteResult
    {
        return $this->connection->deleteStream(
            $streamId,
            ExpectedVersion::Any,
            true,
            null === $login && null === $password
                ? null
                : new UserCredentials($login, $password)
        );
    }

    /** @return EventData[] */
    protected function createEvents(): array
    {
        return [
            new EventData(
                null,
                'some-type',
                false,
                '',
                ''
            ),
        ];
    }

    protected function expectExceptionFromCallback(string $expectedException, Closure $callback): void
    {
        $this->expectException($expectedException);

        $callback();
    }

    private function adminUser(): UserCredentials
    {
        return new UserCredentials(
            \getenv('ES_USER'),
            \getenv('ES_PASS')
        );
    }
}
