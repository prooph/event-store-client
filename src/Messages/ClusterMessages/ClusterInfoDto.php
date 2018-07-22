<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Messages\ClusterMessages;

use Prooph\EventStoreClient\Exception\InvalidArgumentException;

class ClusterInfoDto
{
    /** @var MemberInfoDto[] */
    private $members = [];

    public function __construct(array $members = [])
    {
        foreach ($members as $member) {
            if (! $member instanceof MemberInfoDto) {
                throw new InvalidArgumentException('Expected an array of MemberInfoDto');
            }

            $this->members[] = $member;
        }
    }

    /** @return MemberInfoDto[] */
    public function members(): array
    {
        return $this->members;
    }
}
