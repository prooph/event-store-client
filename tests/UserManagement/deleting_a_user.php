<?php
/**
 * This file is part of the prooph/event-store-client.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient\UserManagement;

use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Exception\UserCommandFailedException;
use Prooph\EventStoreClient\Transport\Http\HttpStatusCode;
use Prooph\EventStoreClient\UserManagement\UserDetails;
use ProophTest\EventStoreClient\DefaultData;
use Ramsey\Uuid\Uuid;
use function Amp\call;
use function Amp\Promise\wait;

class deleting_a_user extends TestWithNode
{
    /**
     * @test
     * @throws \Throwable
     */
    public function deleting_non_existing_user_throws(): void
    {
        wait(call(function () {
            $this->expectException(UserCommandFailedException::class);

            try {
                yield $this->manager->deleteUserAsync(Uuid::uuid4()->toString(), DefaultData::adminCredentials());
            } catch (UserCommandFailedException $e) {
                $this->assertSame(HttpStatusCode::NotFound, $e->httpStatusCode());

                throw $e;
            }
        }));
    }

    /**
     * @test
     * @throws \Throwable
     * @doesNotPerformAssertions
     */
    public function deleting_created_user_deletes_it(): void
    {
        wait(call(function () {
            $user = Uuid::uuid4()->toString();

            yield $this->manager->createUserAsync($user, 'ourofull', ['foo', 'bar'], 'ouro', DefaultData::adminCredentials());
            yield $this->manager->deleteUserAsync($user, DefaultData::adminCredentials());
        }));
    }

    /**
     * @test
     * @throws \Throwable
     */
    public function deleting_empty_user_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager->deleteUserAsync('', DefaultData::adminCredentials());
    }

    /**
     * @test
     * @throws \Throwable
     */
    public function can_delete_a_user(): void
    {
        wait(call(function () {
            yield $this->manager->createUserAsync(
                'ouro',
                'ouro',
                ['foo', 'bar'],
                'ouro',
                DefaultData::adminCredentials()
            );

            $x = yield $this->manager->getUserAsync('ouro', DefaultData::adminCredentials());

            $this->assertInstanceOf(UserDetails::class, $x);

            yield $this->manager->deleteUserAsync('ouro', DefaultData::adminCredentials());

            $this->expectException(UserCommandFailedException::class);

            yield $this->manager->getUserAsync('ouro', DefaultData::adminCredentials());
        }));
    }
}
