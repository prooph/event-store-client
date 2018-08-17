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

namespace Prooph\EventStoreClient\UserManagement;

use JsonSerializable;

/** @internal */
class UserUpdateInformation implements JsonSerializable
{
    /** @var string */
    private $fullName;
    /** @var string[] */
    private $groups;

    public function __construct(string $fullName, array $groups)
    {
        $this->fullName = $fullName;
        $this->groups = $groups;
    }

    public function jsonSerialize(): array
    {
        return [
            'fullName' => $this->fullName,
            'groups' => $this->groups,
        ];
    }
}
