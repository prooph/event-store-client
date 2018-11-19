<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient\UserManagement;

use Prooph\EventStoreClient\Util\UuidGenerator;
use ProophTest\EventStoreClient\DefaultData;

abstract class TestWithUser extends TestWithNode
{
    /** @var string */
    protected $username;

    protected function setUp(): void
    {
        parent::setUp();

        $this->username = UuidGenerator::generateString();

        $this->manager->createUser(
            $this->username,
            'name',
            ['foo', 'admins'],
            'password',
            DefaultData::adminCredentials()
        );
    }
}
