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

use Prooph\EventStore\Exception\InvalidArgumentException;
use Ramsey\Uuid\Uuid;

/** @psalm-suppress PropertyNotSetInConstructor */
class MemberInfoDto
{
    private string $instanceId;

    private string $timeStamp;

    private VNodeState $state;

    private bool $isAlive;

    private string $internalTcpIp;

    private int $internalTcpPort;

    private int $internalSecureTcpPort;

    private string $externalTcpIp;

    private int $externalTcpPort;

    private int $externalSecureTcpPort;

    // 20.x cluster info
    public string $httpEndPointIp;

    public int $httpEndPointPort;

    // 5.x cluster info
    public string $externalHttpIp;

    public int $externalHttpPort;

    private int $lastCommitPosition;

    private int $writerCheckpoint;

    private int $chaserCheckpoint;

    private int $epochPosition;

    private int $epochNumber;

    private string $epochId;

    private int $nodePriority;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if ($key === 'state') {
                $this->state = VNodeState::from($value);
            } elseif (\property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    public function instanceId(): string
    {
        return $this->instanceId;
    }

    public function setInstanceId(string $instanceId): void
    {
        if (! Uuid::isValid($instanceId)) {
            throw new InvalidArgumentException('Invalid instance id given');
        }

        $this->instanceId = $instanceId;
    }

    public function timeStamp(): string
    {
        return $this->timeStamp;
    }

    public function setTimeStamp(string $timeStamp): void
    {
        $this->timeStamp = $timeStamp;
    }

    public function state(): VNodeState
    {
        return $this->state;
    }

    public function setState(VNodeState $state): void
    {
        $this->state = $state;
    }

    public function isAlive(): bool
    {
        return $this->isAlive;
    }

    public function setIsAlive(bool $isAlive): void
    {
        $this->isAlive = $isAlive;
    }

    public function internalTcpIp(): string
    {
        return $this->internalTcpIp;
    }

    public function setInternalTcpIp(string $internalTcpIp): void
    {
        $this->internalTcpIp = $internalTcpIp;
    }

    public function internalTcpPort(): int
    {
        return $this->internalTcpPort;
    }

    public function setInternalTcpPort(int $internalTcpPort): void
    {
        $this->internalTcpPort = $internalTcpPort;
    }

    public function internalSecureTcpPort(): int
    {
        return $this->internalSecureTcpPort;
    }

    public function setInternalSecureTcpPort(int $internalSecureTcpPort): void
    {
        $this->internalSecureTcpPort = $internalSecureTcpPort;
    }

    public function externalTcpIp(): string
    {
        return $this->externalTcpIp;
    }

    public function setExternalTcpIp(string $externalTcpIp): void
    {
        $this->externalTcpIp = $externalTcpIp;
    }

    public function externalTcpPort(): int
    {
        return $this->externalTcpPort;
    }

    public function setExternalTcpPort(int $externalTcpPort): void
    {
        $this->externalTcpPort = $externalTcpPort;
    }

    public function externalSecureTcpPort(): int
    {
        return $this->externalSecureTcpPort;
    }

    public function setExternalSecureTcpPort(int $externalSecureTcpPort): void
    {
        $this->externalSecureTcpPort = $externalSecureTcpPort;
    }

    public function internalHttpIp(): string
    {
        return $this->internalHttpIp;
    }

    public function setInternalHttpIp(string $internalHttpIp): void
    {
        $this->internalHttpIp = $internalHttpIp;
    }

    public function internalHttpPort(): int
    {
        return $this->internalHttpPort;
    }

    public function setInternalHttpPort(int $internalHttpPort): void
    {
        $this->internalHttpPort = $internalHttpPort;
    }

    public function externalHttpIp(): string
    {
        return $this->externalHttpIp;
    }

    public function setExternalHttpIp(string $externalHttpIp): void
    {
        $this->externalHttpIp = $externalHttpIp;
    }

    public function externalHttpPort(): int
    {
        return $this->externalHttpPort;
    }

    public function setExternalHttpPort(int $externalHttpPort): void
    {
        $this->externalHttpPort = $externalHttpPort;
    }

    public function lastCommitPosition(): int
    {
        return $this->lastCommitPosition;
    }

    public function setLastCommitPosition(int $lastCommitPosition): void
    {
        $this->lastCommitPosition = $lastCommitPosition;
    }

    public function writerCheckpoint(): int
    {
        return $this->writerCheckpoint;
    }

    public function setWriterCheckpoint(int $writerCheckpoint): void
    {
        $this->writerCheckpoint = $writerCheckpoint;
    }

    public function chaserCheckpoint(): int
    {
        return $this->chaserCheckpoint;
    }

    public function setChaserCheckpoint(int $chaserCheckpoint): void
    {
        $this->chaserCheckpoint = $chaserCheckpoint;
    }

    public function epochPosition(): int
    {
        return $this->epochPosition;
    }

    public function setEpochPosition(int $epochPosition): void
    {
        $this->epochPosition = $epochPosition;
    }

    public function epochNumber(): int
    {
        return $this->epochNumber;
    }

    public function setEpochNumber(int $epochNumber): void
    {
        $this->epochNumber = $epochNumber;
    }

    public function epochId(): string
    {
        return $this->epochId;
    }

    public function setEpochId(string $epochId): void
    {
        if (! Uuid::isValid($epochId)) {
            throw new InvalidArgumentException('Invalid epoch id given');
        }

        $this->epochId = $epochId;
    }

    public function nodePriority(): int
    {
        return $this->nodePriority;
    }

    public function setNodePriority(int $nodePriority): void
    {
        $this->nodePriority = $nodePriority;
    }

    public function httpAddress(): string
    {
        return $this->httpEndPointIp ?? $this->externalHttpIp;
    }

    public function httpPort(): int
    {
        return $this->httpEndPointPort ?? $this->externalHttpPort();
    }
}
