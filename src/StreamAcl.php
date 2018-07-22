<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Prooph\EventStoreClient\Common\SystemMetadata;
use Webmozart\Assert\Assert;

class StreamAcl
{
    /**
     * Roles and users permitted to read the stream
     * @var string[]
     */
    private $readRoles;

    /**
     * Roles and users permitted to write to the stream
     * @var string[]
     */
    private $writeRoles;

    /**
     * Roles and users permitted to delete the stream
     * @var string[]
     */
    private $deleteRoles;

    /**
     * Roles and users permitted to read stream metadata
     * @var string[]
     */
    private $metaReadRoles;

    /**
     * Roles and users permitted to write stream metadata
     * @var string[]
     */
    private $metaWriteRoles;

    public function __construct(
        array $readRoles,
        array $writeRoles,
        array $deleteRoles,
        array $metaReadRoles,
        array $metaWriteRoles
    ) {
        Assert::allStringNotEmpty($readRoles);
        Assert::allStringNotEmpty($writeRoles);
        Assert::allStringNotEmpty($deleteRoles);
        Assert::allStringNotEmpty($metaReadRoles);
        Assert::allStringNotEmpty($metaWriteRoles);

        $this->readRoles = $readRoles;
        $this->writeRoles = $writeRoles;
        $this->deleteRoles = $deleteRoles;
        $this->metaReadRoles = $metaReadRoles;
        $this->metaWriteRoles = $metaWriteRoles;
    }

    /**
     * @return string[]
     */
    public function readRoles(): array
    {
        return $this->readRoles;
    }

    /**
     * @return string[]
     */
    public function writeRoles(): array
    {
        return $this->writeRoles;
    }

    /**
     * @return string[]
     */
    public function deleteRoles(): array
    {
        return $this->deleteRoles;
    }

    /**
     * @return string[]
     */
    public function metaReadRoles(): array
    {
        return $this->metaReadRoles;
    }

    /**
     * @return string[]
     */
    public function metaWriteRoles(): array
    {
        return $this->metaWriteRoles;
    }

    public function toArray(): array
    {
        return [
            SystemMetadata::AclRead => $this->readRoles,
            SystemMetadata::AclWrite => $this->writeRoles,
            SystemMetadata::AclDelete => $this->deleteRoles,
            SystemMetadata::AclMetaRead => $this->metaReadRoles,
            SystemMetadata::AclMetaWrite => $this->metaWriteRoles,
        ];
    }

    public static function fromArray(array $data): StreamAcl
    {
        $values = [
            SystemMetadata::AclRead,
            SystemMetadata::AclWrite,
            SystemMetadata::AclDelete,
            SystemMetadata::AclMetaRead,
            SystemMetadata::AclMetaWrite,
        ];

        foreach ($values as $value) {
            if (! isset($data[$value])) {
                throw new \InvalidArgumentException($value . ' is missing');
            }

            if (! \is_array($data[$value])) {
                throw new \InvalidArgumentException($value . ' is not an array');
            }
        }

        return new self(
            $data[SystemMetadata::AclRead],
            $data[SystemMetadata::AclWrite],
            $data[SystemMetadata::AclDelete],
            $data[SystemMetadata::AclMetaRead],
            $data[SystemMetadata::AclMetaWrite]
        );
    }
}
