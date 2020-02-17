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

namespace ProophTest\EventStoreClient\UserManagement;

use function Amp\call;
use function Amp\Promise\wait;
use Closure;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\EndPoint;
use Prooph\EventStoreClient\UserManagement\UsersManager;
use Throwable;

abstract class TestWithNode extends TestCase
{
    protected UsersManager $manager;

    protected function setUp(): void
    {
        $this->manager = new UsersManager(
            new EndPoint(
                (string) \getenv('ES_HOST'),
                (int) \getenv('ES_HTTP_PORT')
            ),
            5000
        );
    }

    /** @throws Throwable */
    protected function execute(Closure $function): void
    {
        wait(call(function () use ($function): Generator {
            yield from $function();
        }));
    }
}
