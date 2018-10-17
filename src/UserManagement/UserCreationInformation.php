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

namespace Prooph\EventStoreClient\UserManagement;

use JsonSerializable;

/** @internal */
final class UserCreationInformation implements JsonSerializable
{
    /** @var string */
    private $loginName;
    /** @var string */
    private $fullName;
    /** @var string[] */
    private $groups;
    /** @var string */
    private $password;

    public function __construct(string $loginName, string $fullName, array $groups, string $password)
    {
        $this->loginName = $loginName;
        $this->fullName = $fullName;
        $this->groups = $groups;
        $this->password = $password;
    }

    public function loginName(): string
    {
        return $this->loginName;
    }

    public function fullName(): string
    {
        return $this->fullName;
    }

    /** @return string[] */
    public function groups(): array
    {
        return $this->groups;
    }

    public function password(): string
    {
        return $this->password;
    }

    public function jsonSerialize(): array
    {
        return [
            'loginName' => $this->loginName,
            'fullName' => $this->fullName,
            'groups' => $this->groups,
            'password' => $this->password,
        ];
    }
}
