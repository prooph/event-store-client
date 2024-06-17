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

namespace ProophTest\EventStoreClient;

use Prooph\EventStore\UserCredentials;

class DefaultData
{
    public static function adminUsername(): string
    {
        return SystemUsers::Admin;
    }

    public static function adminPassword(): string
    {
        return SystemUsers::DefaultAdminPassword;
    }

    public static function adminCredentials(): UserCredentials
    {
        return new UserCredentials(self::adminUsername(), self::adminPassword());
    }
}
