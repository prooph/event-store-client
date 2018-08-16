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

//    /**
//     * @test
//     * @throws \Throwable
//     */
//    public function deleting_created_user_deletes_it(): void
//    {
//        var user = Guid.NewGuid().ToString();
//        Assert.DoesNotThrow(() => _manager.CreateUserAsync(user, "ourofull", new[] { "foo", "bar" }, "ouro", new UserCredentials("admin", "changeit")).Wait());
//        Assert.DoesNotThrow(() => _manager.DeleteUserAsync(user, new UserCredentials("admin", "changeit")).Wait());
//    }
//

    /**
     * @test
     * @throws \Throwable
     */
    public function deleting_empty_user_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager->deleteUserAsync('', DefaultData::adminCredentials());
    }

//
//    /**
//     * @test
//     * @throws \Throwable
//     */
//    public function can_delete_a_user(): void
//    {
//        _manager.CreateUserAsync("ouro", "ouro", new[] { "foo", "bar" }, "ouro", new UserCredentials("admin", "changeit")).Wait();
//        Assert.DoesNotThrow(() =>
//        {
//            var x =_manager.GetUserAsync("ouro", new UserCredentials("admin", "changeit")).Result;
//        });
//        _manager.DeleteUserAsync("ouro", new UserCredentials("admin", "changeit")).Wait();
//
//        var ex = Assert.Throws<AggregateException>(
//            () => { var x = _manager.GetUserAsync("ouro", new UserCredentials("admin", "changeit")).Result; }
//        );
//        Assert.AreEqual(HttpStatusCode.NotFound, ((UserCommandFailedException) ex.InnerException.InnerException).HttpStatusCode);
//    }
}
