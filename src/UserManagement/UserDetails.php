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

use DateTimeImmutable;
use Exception;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;

final class UserDetails
{
    /** @var string */
    private $loginName;
    /** @var string */
    private $fullName;
    /** @var string[] */
    private $groups = [];
    /** @var DateTimeImmutable */
    private $dateLastUpdated;
    /** @var bool */
    private $disabled;
    /** @var RelLink[] */
    private $links = [];

    public function __construct(
        string $loginName,
        string $fullName,
        array $groups,
        ?DateTimeImmutable $dateLastUpdated,
        bool $disabled,
        array $links
    ) {
        foreach ($groups as $group) {
            if (! \is_string($group)) {
                throw new InvalidArgumentException('Expected an array of strings for group');
            }
        }

        foreach ($links as $link) {
            if (! $link instanceof RelLink) {
                throw new InvalidArgumentException('Expected an array of RelLink for links');
            }
        }

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

    /** @return string[] */
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

    /** @return RelLink[] */
    public function links(): array
    {
        return $this->links;
    }

    /** @throws Exception if rel not found */
    public function getRelLink(string $rel): string
    {
        $rel = \strtolower($rel);

        foreach ($this->links() as $link) {
            if (\strtolower($link->rel()) === $rel) {
                return $link->href();
            }
        }

        throw new Exception('rel not found');
    }

    private function addGroup(string $group): void
    {
        $this->groups[] = $group;
    }

    private function addLink(RelLink $link): void
    {
        $this->links[] = $link;
    }
}
