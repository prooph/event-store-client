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

use DateTimeImmutable;

final class UserDetails
{
    /** @var string */
    private $loginName;
    /** @var string */
    private $fullName;
    /** @var string[] */
    private $groups;
    /** @var DateTimeImmutable */
    private $dateLastUpdated;
    /** @var bool */
    private $disabled;
    /** @var string[] */
    private $links;

    public function __construct(
        string $loginName,
        string $fullName,
        array $groups,
        DateTimeImmutable $dateLastUpdated,
        bool $disabled,
        array $links
    ) {
        $this->loginName = $loginName;
        $this->fullName = $fullName;
        $this->groups = $groups;
        $this->dateLastUpdated = $dateLastUpdated;
        $this->disabled = $disabled;
        $this->links = $links;
    }

    public function loginName(): string
    {
        return $this->loginName;
    }

    public function fullName(): string
    {
        return $this->fullName;
    }

    public function groups(): array
    {
        return $this->groups;
    }

    public function dateLastUpdated(): DateTimeImmutable
    {
        return $this->dateLastUpdated;
    }

    public function disabled(): bool
    {
        return $this->disabled;
    }

    public function links(): array
    {
        return $this->links;
    }
}
