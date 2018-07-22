<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\SystemData;

use Prooph\EventStoreClient\UserCredentials;

class TcpPackage
{
    public const CommandOffset = 0;
    public const DataOffset = 4;
    public const FlagsOffset = self::CommandOffset + 1;
    public const CorrelationOffset = self::FlagsOffset + 1;
    public const AuthOffset = self::CorrelationOffset + 16;

    public const MandatorySize = self::AuthOffset;

    /** @var TcpCommand */
    private $command;
    /** @var TcpFlags */
    private $flags;
    /** @var string */
    private $correlationId;
    /** @var string */
    private $data;
    /** @var UserCredentials|null */
    private $credentials;

    public function __construct(
        TcpCommand $command,
        TcpFlags $flags,
        string $correlationId,
        string $data = '',
        UserCredentials $credentials = null
    ) {
        $this->command = $command;
        $this->flags = $flags;
        $this->correlationId = $correlationId;
        $this->data = $data;
        $this->credentials = $credentials;
    }

    public function command(): TcpCommand
    {
        return $this->command;
    }

    public function flags(): TcpFlags
    {
        return $this->flags;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function data(): string
    {
        return $this->data;
    }

    public function credentials(): ?Usercredentials
    {
        return $this->credentials;
    }
}
