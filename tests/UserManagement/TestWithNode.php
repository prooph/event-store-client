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

namespace ProophTest\EventStoreClient\UserManagement;

use Amp\PHPUnit\AsyncTestCase;
use Prooph\EventStore\EndPoint;
use Prooph\EventStoreClient\UserManagement\UsersManager;

abstract class TestWithNode extends AsyncTestCase
{
    protected UsersManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new UsersManager(
            new EndPoint(
                (string) \getenv('ES_HOST'),
                (int) \getenv('ES_HTTP_PORT')
            ),
            5
        );
    }
}
