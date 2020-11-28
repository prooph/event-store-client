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
use Amp\Deferred;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use Closure;
use Generator;
use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\Async\EventStoreTransaction;
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventStoreSubscription;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Position;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\UserManagement\UsersManager;
use ProophTest\EventStoreClient\Helper\TestConnection;

abstract class AuthenticationTestCase extends AsyncTestCase
{
    protected ?EventStoreConnection $connection;
    protected ?UserCredentials $userCredentials = null;

    protected function setUpAsync(): Generator
    {
        $manager = new UsersManager(
            TestConnection::httpEndPoint(),
            5000,
            false,
            false,
            $this->adminUser()
        );

        yield $manager->createUserAsync(
            'user1',
            'Test User 1',
            [],
            'pa$$1'
        );

        yield $manager->createUserAsync(
            'user2',
            'Test User 2',
            [],
            'pa$$2'
        );

        yield $manager->createUserAsync(
            'adm',
            'Administrator User',
            [SystemRoles::ADMINS],
            'admpa$$'
        );

        $connection = TestConnection::create($this->adminUser());
        yield $connection->connectAsync();

        yield $connection->setStreamMetadataAsync(
            'noacl-stream',
            ExpectedVersion::ANY,
            StreamMetadata::create()->build()
        );
        yield $connection->setStreamMetadataAsync(
            'read-stream',
            ExpectedVersion::ANY,
            StreamMetadata::create()->setReadRoles('user1')->build()
        );
        yield $connection->setStreamMetadataAsync(
            'write-stream',
            ExpectedVersion::ANY,
            StreamMetadata::create()->setWriteRoles('user1')->build()
        );
        yield $connection->setStreamMetadataAsync(
            'metaread-stream',
            ExpectedVersion::ANY,
            StreamMetadata::create()->setMetadataReadRoles('user1')->build()
        );
        yield $connection->setStreamMetadataAsync(
            'metawrite-stream',
            ExpectedVersion::ANY,
            StreamMetadata::create()->setMetadataWriteRoles('user1')->build()
        );

        yield $connection->setStreamMetadataAsync(
            '$all',
            ExpectedVersion::ANY,
            StreamMetadata::create()->setReadRoles('user1')->build(),
            new UserCredentials('adm', 'admpa$$')
        );

        yield $connection->setStreamMetadataAsync(
            '$system-acl',
            ExpectedVersion::ANY,
            StreamMetadata::create()
                ->setReadRoles('user1')
                ->setWriteRoles('user1')
                ->setMetadataReadRoles('user1')
                ->setMetadataWriteRoles('user1')
                ->build(),
            new UserCredentials('adm', 'admpa$$')
        );
        yield $connection->setStreamMetadataAsync(
            '$system-adm',
            ExpectedVersion::ANY,
            StreamMetadata::create()
                ->setReadRoles(SystemRoles::ADMINS)
                ->setWriteRoles(SystemRoles::ADMINS)
                ->setMetadataReadRoles(SystemRoles::ADMINS)
                ->setMetadataWriteRoles(SystemRoles::ADMINS)
                ->build(),
            new UserCredentials('adm', 'admpa$$')
        );

        yield $connection->setStreamMetadataAsync(
            'normal-all',
            ExpectedVersion::ANY,
            StreamMetadata::create()
                ->setReadRoles(SystemRoles::ALL)
                ->setWriteRoles(SystemRoles::ALL)
                ->setMetadataReadRoles(SystemRoles::ALL)
                ->setMetadataWriteRoles(SystemRoles::ALL)
                ->build()
        );
        yield $connection->setStreamMetadataAsync(
            '$system-all',
            ExpectedVersion::ANY,
            StreamMetadata::create()
                ->setReadRoles(SystemRoles::ALL)
                ->setWriteRoles(SystemRoles::ALL)
                ->setMetadataReadRoles(SystemRoles::ALL)
                ->setMetadataWriteRoles(SystemRoles::ALL)
                ->build(),
            new UserCredentials('adm', 'admpa$$')
        );

        //$connection->close();

        $this->connection = TestConnection::create($this->userCredentials);

        yield $this->connection->connectAsync();
    }

    protected function tearDownAsync(): Generator
    {
        $this->userCredentials = null;
        $this->connection->close();

        $manager = new UsersManager(
            TestConnection::httpEndPoint(),
            5000,
            false,
            false,
            $this->adminUser()
        );

        yield $manager->deleteUserAsync('user1');
        yield $manager->deleteUserAsync('user2');
        yield $manager->deleteUserAsync('adm');

        $connection = TestConnection::create($this->adminUser());
        yield $connection->connectAsync();

        yield $connection->setStreamMetadataAsync(
            '$all',
            ExpectedVersion::ANY,
            StreamMetadata::create()->build(),
            $this->adminUser()
        );

        yield $connection->setStreamMetadataAsync(
            '$system-acl',
            ExpectedVersion::ANY,
            StreamMetadata::create()->build(),
            $this->adminUser()
        );

        yield $connection->setSystemSettingsAsync(
            new SystemSettings(),
            $this->adminUser()
        );

        //$connection->close();
    }

    protected function readEvent(string $streamId, ?string $login, ?string $password): Promise
    {
        return $this->connection->readEventAsync(
            $streamId,
            -1,
            false,
            null === $login && null === $password
                ? null
                : new UserCredentials($login, $password)
        );
    }

    protected function readStreamForward(string $streamId, ?string $login, ?string $password): Promise
    {
        return $this->connection->readStreamEventsForwardAsync(
            $streamId,
            0,
            1,
            false,
            null === $login && null === $password
                ? null
                : new UserCredentials($login, $password)
        );
    }

    protected function readStreamBackward(string $streamId, ?string $login, ?string $password): Promise
    {
        return $this->connection->readStreamEventsBackwardAsync(
            $streamId,
            0,
            1,
            false,
            null === $login && null === $password
                ? null
                : new UserCredentials($login, $password)
        );
    }

    protected function writeStream(string $streamId, ?string $login, ?string $password): Promise
    {
        return $this->connection->appendToStreamAsync(
            $streamId,
            ExpectedVersion::ANY,
            $this->createEvents(),
            null === $login && null === $password
                ? null
                : new UserCredentials($login, $password)
        );
    }

    /** @return Promise<EventStoreTransaction> */
    protected function transStart(string $streamId, ?string $login, ?string $password): Promise
    {
        return $this->connection->startTransactionAsync(
            $streamId,
            ExpectedVersion::ANY,
            null === $login && null === $password
                ? null
                : new UserCredentials($login, $password)
        );
    }

    protected function readAllForward(?string $login, ?string $password): Promise
    {
        return $this->connection->readAllEventsForwardAsync(
            Position::start(),
            1,
            false,
            null === $login && null === $password
                ? null
                : new UserCredentials($login, $password)
        );
    }

    protected function readAllBackward(?string $login, ?string $password): Promise
    {
        return $this->connection->readAllEventsBackwardAsync(
            Position::end(),
            1,
            false,
            null === $login && null === $password
                ? null
                : new UserCredentials($login, $password)
        );
    }

    protected function readMeta(string $streamId, ?string $login, ?string $password): Promise
    {
        return $this->connection->getRawStreamMetadataAsync(
            $streamId,
            null === $login && null === $password
                ? null
                : new UserCredentials($login, $password)
        );
    }

    protected function writeMeta(string $streamId, ?string $login, ?string $password, ?string $metawriteRole): Promise
    {
        return $this->connection->setStreamMetadataAsync(
            $streamId,
            ExpectedVersion::ANY,
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

    protected function subscribeToStream(string $streamId, ?string $login, ?string $password): Promise
    {
        return $this->connection->subscribeToStreamAsync(
            $streamId,
            false,
            function (
                EventStoreSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ): Promise {
                return new Success();
            },
            null,
            null === $login && null === $password
                ? null
                : new UserCredentials($login, $password)
        );
    }

    protected function subscribeToAll(?string $login, ?string $password): Promise
    {
        return $this->connection->subscribeToAllAsync(
            false,
            function (
                EventStoreSubscription $subscription,
                ResolvedEvent $resolvedEvent
            ): Promise {
                return new Success();
            },
            null,
            null === $login && null === $password
                ? null
                : new UserCredentials($login, $password)
        );
    }

    /** @return Promise<string> */
    protected function createStreamWithMeta(StreamMetadata $metadata, ?string $streamPrefix = null): Promise
    {
        $stream = ($streamPrefix ?? '') . $this->getName();

        $deferred = new Deferred();

        $promise = $this->connection->setStreamMetadataAsync(
            $stream,
            ExpectedVersion::NO_STREAM,
            $metadata,
            new UserCredentials('adm', 'admpa$$')
        );

        $promise->onResolve(function () use ($deferred, $stream): void {
            $deferred->resolve($stream);
        });

        return $deferred->promise();
    }

    protected function deleteStream(string $streamId, ?string $login, ?string $password): Promise
    {
        return $this->connection->deleteStreamAsync(
            $streamId,
            ExpectedVersion::ANY,
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

    protected function expectExceptionFromCallback(string $expectedException, Closure $callback): Promise
    {
        return call(function () use ($expectedException, $callback): Generator {
            $this->expectException($expectedException);

            yield $callback();
        });
    }

    protected function expectNoExceptionFromCallback(Closure $callback): Promise
    {
        $deferred = new Deferred();

        $promise = $callback();
        $promise->onResolve(function ($e, $r) use ($deferred): void {
            $this->assertNull($e);
            $deferred->resolve($r);
        });

        return $deferred->promise();
    }

    private function adminUser(): UserCredentials
    {
        return new UserCredentials(
            \getenv('ES_USER'),
            \getenv('ES_PASS')
        );
    }
}
