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

use Prooph\EventStoreClient\Common\SystemMetadata;
use Prooph\EventStoreClient\Common\SystemRoles;

class SystemSettings
{
    /**
     * Default access control list for new user streams.
     * @var StreamAcl
     */
    private $userStreamAcl;

    /**
     * Default access control list for new system streams.
     * @var StreamAcl
     */
    private $systemStreamAcl;

    public static function default(): SystemSettings
    {
        return new self(
            new StreamAcl(
                [SystemRoles::All],
                [SystemRoles::All],
                [SystemRoles::All],
                [SystemRoles::All],
                [SystemRoles::All]
            ),
            new StreamAcl(
                [SystemRoles::All, SystemRoles::Admins],
                [SystemRoles::All, SystemRoles::Admins],
                [SystemRoles::All, SystemRoles::Admins],
                [SystemRoles::All, SystemRoles::Admins],
                [SystemRoles::All, SystemRoles::Admins]
            )
        );
    }

    public function __construct(StreamAcl $userStreamAcl, StreamAcl $systemStreamAcl)
    {
        $this->userStreamAcl = $userStreamAcl;
        $this->systemStreamAcl = $systemStreamAcl;
    }

    public function userStreamAcl(): StreamAcl
    {
        return $this->userStreamAcl;
    }

    public function systemStreamAcl(): StreamAcl
    {
        return $this->systemStreamAcl;
    }

    public function toArray(): array
    {
        return [
            SystemMetadata::UserStreamAcl => $this->userStreamAcl->toArray(),
            SystemMetadata::SystemStreamAcl => $this->systemStreamAcl->toArray(),
        ];
    }

    public static function fromArray(array $data): SystemSettings
    {
        if (! isset($data[SystemMetadata::UserStreamAcl])) {
            throw new \InvalidArgumentException(SystemMetadata::UserStreamAcl . ' is missing');
        }

        if (! isset($data[SystemMetadata::SystemStreamAcl])) {
            throw new \InvalidArgumentException(SystemMetadata::SystemStreamAcl . ' is missing');
        }

        return new self(
            StreamAcl::fromArray($data[SystemMetadata::UserStreamAcl]),
            StreamAcl::fromArray($data[SystemMetadata::SystemStreamAcl])
        );
    }
}
