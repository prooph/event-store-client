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

namespace ProophTest\EventStoreClient\UserManagement;

use Generator;
use Prooph\EventStore\Util\Guid;
use ProophTest\EventStoreClient\DefaultData;
use Throwable;
use function Amp\call;
use function Amp\Promise\wait;

abstract class TestWithUser extends TestWithNode
{
    /** @var string */
    protected $username;

    protected function setUp(): void
    {
        parent::setUp();

        $this->username = Guid::generateString();
    }

    /** @throws Throwable */
    protected function execute(callable $function): void
    {
        wait(call(function () use ($function): Generator {
            yield $this->manager->createUserAsync(
                $this->username,
                'name',
                ['foo', 'admins'],
                'password',
                DefaultData::adminCredentials()
            );

            yield from $function();
        }));
    }
}
