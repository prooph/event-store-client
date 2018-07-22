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

namespace Prooph\EventStoreClient\SystemData;

final class TcpCommand
{
    public const OPTIONS = [
        'HeartbeatRequestCommand' => 0x01,
        'HeartbeatResponseCommand' => 0x02,

        'Ping' => 0x03,
        'Pong' => 0x04,

        'PrepareAck' => 0x05,
        'CommitAck' => 0x06,

        'SlaveAssignment' => 0x07,
        'CloneAssignment' => 0x08,

        'SubscribeReplica' => 0x10,
        'ReplicaLogPositionAck' => 0x11,
        'CreateChunk' => 0x12,
        'RawChunkBulk' => 0x13,
        'DataChunkBulk' => 0x14,
        'ReplicaSubscriptionRetry' => 0x15,
        'ReplicaSubscribed' => 0x16,

        'WriteEvents' => 0x82,
        'WriteEventsCompleted' => 0x83,

        'TransactionStart' => 0x84,
        'TransactionStartCompleted' => 0x85,
        'TransactionWrite' => 0x86,
        'TransactionWriteCompleted' => 0x87,
        'TransactionCommit' => 0x88,
        'TransactionCommitCompleted' => 0x89,

        'DeleteStream' => 0x8A,
        'DeleteStreamCompleted' => 0x8B,

        'ReadEvent' => 0xB0,
        'ReadEventCompleted' => 0xB1,
        'ReadStreamEventsForward' => 0xB2,
        'ReadStreamEventsForwardCompleted' => 0xB3,
        'ReadStreamEventsBackward' => 0xB4,
        'ReadStreamEventsBackwardCompleted' => 0xB5,
        'ReadAllEventsForward' => 0xB6,
        'ReadAllEventsForwardCompleted' => 0xB7,
        'ReadAllEventsBackward' => 0xB8,
        'ReadAllEventsBackwardCompleted' => 0xB9,

        'SubscribeToStream' => 0xC0,
        'SubscriptionConfirmation' => 0xC1,
        'StreamEventAppeared' => 0xC2,
        'UnsubscribeFromStream' => 0xC3,
        'SubscriptionDropped' => 0xC4,
        'ConnectToPersistentSubscription' => 0xC5,
        'PersistentSubscriptionConfirmation' => 0xC6,
        'PersistentSubscriptionStreamEventAppeared' => 0xC7,
        'CreatePersistentSubscription' => 0xC8,
        'CreatePersistentSubscriptionCompleted' => 0xC9,
        'DeletePersistentSubscription' => 0xCA,
        'DeletePersistentSubscriptionCompleted' => 0xCB,
        'PersistentSubscriptionAckEvents' => 0xCC,
        'PersistentSubscriptionNakEvents' => 0xCD,
        'UpdatePersistentSubscription' => 0xCE,
        'UpdatePersistentSubscriptionCompleted' => 0xCF,

        'ScavengeDatabase' => 0xD0,
        'ScavengeDatabaseCompleted' => 0xD1,

        'BadRequest' => 0xF0,
        'NotHandled' => 0xF1,
        'Authenticate' => 0xF2,
        'Authenticated' => 0xF3,
        'NotAuthenticatedException' => 0xF4,
        'IdentifyClient' => 0xF5,
        'ClientIdentified' => 0xF6,
    ];

    public const HeartbeatRequestCommand = 0x01;
    public const HeartbeatResponseCommand = 0x02;

    public const Ping = 0x03;
    public const Pong = 0x04;

    public const PrepareAck = 0x05;
    public const CommitAck = 0x06;

    public const SlaveAssignment = 0x07;
    public const CloneAssignment = 0x08;

    public const SubscribeReplica = 0x10;
    public const ReplicaLogPositionAck = 0x11;
    public const CreateChunk = 0x12;
    public const RawChunkBulk = 0x13;
    public const DataChunkBulk = 0x14;
    public const ReplicaSubscriptionRetry = 0x15;
    public const ReplicaSubscribed = 0x16;

    public const WriteEvents = 0x82;
    public const WriteEventsCompleted = 0x83;

    public const TransactionStart = 0x84;
    public const TransactionStartCompleted = 0x85;
    public const TransactionWrite = 0x86;
    public const TransactionWriteCompleted = 0x87;
    public const TransactionCommit = 0x88;
    public const TransactionCommitCompleted = 0x89;

    public const DeleteStream = 0x8A;
    public const DeleteStreamCompleted = 0x8B;

    public const ReadEvent = 0xB0;
    public const ReadEventCompleted = 0xB1;
    public const ReadStreamEventsForward = 0xB2;
    public const ReadStreamEventsForwardCompleted = 0xB3;
    public const ReadStreamEventsBackward = 0xB4;
    public const ReadStreamEventsBackwardCompleted = 0xB5;
    public const ReadAllEventsForward = 0xB6;
    public const ReadAllEventsForwardCompleted = 0xB7;
    public const ReadAllEventsBackward = 0xB8;
    public const ReadAllEventsBackwardCompleted = 0xB9;

    public const SubscribeToStream = 0xC0;
    public const SubscriptionConfirmation = 0xC1;
    public const StreamEventAppeared = 0xC2;
    public const UnsubscribeFromStream = 0xC3;
    public const SubscriptionDropped = 0xC4;
    public const ConnectToPersistentSubscription = 0xC5;
    public const PersistentSubscriptionConfirmation = 0xC6;
    public const PersistentSubscriptionStreamEventAppeared = 0xC7;
    public const CreatePersistentSubscription = 0xC8;
    public const CreatePersistentSubscriptionCompleted = 0xC9;
    public const DeletePersistentSubscription = 0xCA;
    public const DeletePersistentSubscriptionCompleted = 0xCB;
    public const PersistentSubscriptionAckEvents = 0xCC;
    public const PersistentSubscriptionNakEvents = 0xCD;
    public const UpdatePersistentSubscription = 0xCE;
    public const UpdatePersistentSubscriptionCompleted = 0xCF;

    public const ScavengeDatabase = 0xD0;
    public const ScavengeDatabaseCompleted = 0xD1;

    public const BadRequest = 0xF0;
    public const NotHandled = 0xF1;
    public const Authenticate = 0xF2;
    public const Authenticated = 0xF3;
    public const NotAuthenticatedException = 0xF4;
    public const IdentifyClient = 0xF5;
    public const ClientIdentified = 0xF6;

    private $name;
    private $value;

    private function __construct(string $name)
    {
        $this->name = $name;
        $this->value = self::OPTIONS[$name];
    }

    public static function heartbeatRequestCommand(): self
    {
        return new self('HeartbeatRequestCommand');
    }

    public static function heartbeatResponseCommand(): self
    {
        return new self('HeartbeatResponseCommand');
    }

    public static function ping(): self
    {
        return new self('Ping');
    }

    public static function pong(): self
    {
        return new self('Pong');
    }

    public static function prepareAck(): self
    {
        return new self('PrepareAck');
    }

    public static function commitAck(): self
    {
        return new self('CommitAck');
    }

    public static function slaveAssignment(): self
    {
        return new self('SlaveAssignment');
    }

    public static function cloneAssignment(): self
    {
        return new self('CloneAssignment');
    }

    public static function subscribeReplica(): self
    {
        return new self('SubscribeReplica');
    }

    public static function replicaLogPositionAck(): self
    {
        return new self('ReplicaLogPositionAck');
    }

    public static function createChunk(): self
    {
        return new self('CreateChunk');
    }

    public static function rawChunkBulk(): self
    {
        return new self('RawChunkBulk');
    }

    public static function dataChunkBulk(): self
    {
        return new self('DataChunkBulk');
    }

    public static function replicaSubscriptionRetry(): self
    {
        return new self('ReplicaSubscriptionRetry');
    }

    public static function replicaSubscribed(): self
    {
        return new self('ReplicaSubscribed');
    }

    public static function writeEvents(): self
    {
        return new self('WriteEvents');
    }

    public static function writeEventsCompleted(): self
    {
        return new self('WriteEventsCompleted');
    }

    public static function transactionStart(): self
    {
        return new self('TransactionStart');
    }

    public static function transactionStartCompleted(): self
    {
        return new self('TransactionStartCompleted');
    }

    public static function transactionWrite(): self
    {
        return new self('TransactionWrite');
    }

    public static function transactionWriteCompleted(): self
    {
        return new self('TransactionWriteCompleted');
    }

    public static function transactionCommit(): self
    {
        return new self('TransactionCommit');
    }

    public static function transactionCommitCompleted(): self
    {
        return new self('TransactionCommitCompleted');
    }

    public static function deleteStream(): self
    {
        return new self('DeleteStream');
    }

    public static function deleteStreamCompleted(): self
    {
        return new self('DeleteStreamCompleted');
    }

    public static function readEvent(): self
    {
        return new self('ReadEvent');
    }

    public static function readEventCompleted(): self
    {
        return new self('ReadEventCompleted');
    }

    public static function readStreamEventsForward(): self
    {
        return new self('ReadStreamEventsForward');
    }

    public static function readStreamEventsForwardCompleted(): self
    {
        return new self('ReadStreamEventsForwardCompleted');
    }

    public static function readStreamEventsBackward(): self
    {
        return new self('ReadStreamEventsBackward');
    }

    public static function readStreamEventsBackwardCompleted(): self
    {
        return new self('ReadStreamEventsBackwardCompleted');
    }

    public static function readAllEventsForward(): self
    {
        return new self('ReadAllEventsForward');
    }

    public static function readAllEventsForwardCompleted(): self
    {
        return new self('ReadAllEventsForwardCompleted');
    }

    public static function readAllEventsBackward(): self
    {
        return new self('ReadAllEventsBackward');
    }

    public static function readAllEventsBackwardCompleted(): self
    {
        return new self('ReadAllEventsBackwardCompleted');
    }

    public static function subscribeToStream(): self
    {
        return new self('SubscribeToStream');
    }

    public static function subscriptionConfirmation(): self
    {
        return new self('SubscriptionConfirmation');
    }

    public static function streamEventAppeared(): self
    {
        return new self('StreamEventAppeared');
    }

    public static function unsubscribeFromStream(): self
    {
        return new self('UnsubscribeFromStream');
    }

    public static function subscriptionDropped(): self
    {
        return new self('SubscriptionDropped');
    }

    public static function connectToPersistentSubscription(): self
    {
        return new self('ConnectToPersistentSubscription');
    }

    public static function persistentSubscriptionConfirmation(): self
    {
        return new self('PersistentSubscriptionConfirmation');
    }

    public static function persistentSubscriptionStreamEventAppeared(): self
    {
        return new self('PersistentSubscriptionStreamEventAppeared');
    }

    public static function createPersistentSubscription(): self
    {
        return new self('CreatePersistentSubscription');
    }

    public static function createPersistentSubscriptionCompleted(): self
    {
        return new self('CreatePersistentSubscriptionCompleted');
    }

    public static function deletePersistentSubscription(): self
    {
        return new self('DeletePersistentSubscription');
    }

    public static function deletePersistentSubscriptionCompleted(): self
    {
        return new self('DeletePersistentSubscriptionCompleted');
    }

    public static function persistentSubscriptionAckEvents(): self
    {
        return new self('PersistentSubscriptionAckEvents');
    }

    public static function persistentSubscriptionNakEvents(): self
    {
        return new self('PersistentSubscriptionNakEvents');
    }

    public static function updatePersistentSubscription(): self
    {
        return new self('UpdatePersistentSubscription');
    }

    public static function updatePersistentSubscriptionCompleted(): self
    {
        return new self('UpdatePersistentSubscriptionCompleted');
    }

    public static function scavengeDatabase(): self
    {
        return new self('ScavengeDatabase');
    }

    public static function scavengeDatabaseCompleted(): self
    {
        return new self('ScavengeDatabaseCompleted');
    }

    public static function badRequest(): self
    {
        return new self('BadRequest');
    }

    public static function notHandled(): self
    {
        return new self('NotHandled');
    }

    public static function authenticate(): self
    {
        return new self('Authenticate');
    }

    public static function authenticated(): self
    {
        return new self('Authenticated');
    }

    public static function notAuthenticatedException(): self
    {
        return new self('NotAuthenticatedException');
    }

    public static function identifyClient(): self
    {
        return new self('IdentifyClient');
    }

    public static function clientIdentified(): self
    {
        return new self('ClientIdentified');
    }

    public static function fromName(string $value): self
    {
        if (! isset(self::OPTIONS[$value])) {
            throw new \InvalidArgumentException('Unknown enum name given');
        }

        return self::{$value}();
    }

    public static function fromValue($value): self
    {
        foreach (self::OPTIONS as $name => $v) {
            if ($v === $value) {
                return self::{$name}();
            }
        }

        throw new \InvalidArgumentException('Unknown enum value given');
    }

    public function equals(TcpCommand $other): bool
    {
        return \get_class($this) === \get_class($other) && $this->name === $other->name;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function value()
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
