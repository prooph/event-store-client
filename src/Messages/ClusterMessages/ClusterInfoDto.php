<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2022 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2022 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\Messages\ClusterMessages;

class ClusterInfoDto
{
    /** @param list<MemberInfoDto> $members */
    public function __construct(private readonly array $members = [])
    {
    }

    /** @return list<MemberInfoDto> */
    public function members(): array
    {
        return $this->members;
    }
}
