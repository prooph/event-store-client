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
class ChangePasswordDetails implements JsonSerializable
{
    /** @var string */
    private $currentPassword;
    /** @var string */
    private $newPassword;

    public function __construct(string $currentPassword, string $newPassword)
    {
        $this->currentPassword = $currentPassword;
        $this->newPassword = $newPassword;
    }

    public function jsonSerialize(): array
    {
        return [
            'currentPassword' => $this->currentPassword,
            'newPassword' => $this->newPassword,
        ];
    }
}
