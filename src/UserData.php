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

namespace Prooph\EventStoreClient;

class UserData
{
    /** @var string */
    private $loginName;
    /** @var string */
    private $fullName;
    /** @var string */
    private $salt;
    /** @var string */
    private $hash;
    /** @var bool */
    private $disabled;
    /** @var string[] */
    private $groups;

    /** @internal */
    public function __construct(
        string $loginName,
        string $fullName,
        string $salt,
        string $hash,
        bool $disabled,
        array $groups
    ) {
        $this->loginName = $loginName;
        $this->fullName = $fullName;
        $this->salt = $salt;
        $this->hash = $hash;
        $this->disabled = $disabled;
        $this->groups = $groups;
    }

    public function loginName(): string
    {
        return $this->loginName;
    }

    public function fullName(): string
    {
        return $this->fullName;
    }

    public function salt(): string
    {
        return $this->salt;
    }

    public function hash(): string
    {
        return $this->hash;
    }

    public function disabled(): bool
    {
        return $this->disabled;
    }

    /** @return string[] */
    public function groups(): array
    {
        return $this->groups;
    }
}
