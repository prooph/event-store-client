<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2019 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient\Security;

use function Amp\call;
use Amp\Promise;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\EventData;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Transport\Http\EndpointExtensions;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\UserManagement\UsersManager;
use ProophTest\EventStoreClient\Helper\TestConnection;
use Throwable;

class AuthenticationTestCase extends TestCase
{
    /** @var EventStoreConnection */
    protected $connection;

    protected function setUp(): void
    {
        $this->connection = TestConnection::create();
        $this->connection->connectAsync();
    }

    protected function tearDown(): void
    {
        $this->connection->close();
        $this->connection = null;
    }

    /** @throws Throwable */
    public static function setUpBeforeClass(): void
    {
        Promise\wait(call(function (): Generator {
            $manager = new UsersManager(
                new EndPoint(
                    (string) \getenv('ES_HOST'),
                    (int) \getenv('ES_HTTP_PORT')
                ),
                5000,
                EndpointExtensions::HTTP_SCHEMA,
                new UserCredentials(
                    \getenv('ES_USER'),
                    \getenv('ES_PASS')
                )
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
        }));
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
}
