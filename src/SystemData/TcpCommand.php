<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2020 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2020 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\SystemData;

use Prooph\EventStore\Exception\InvalidArgumentException;

/** @internal */
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

    public const HEARTBEAT_REQUEST_COMMAND = 0x01;
    public const HEARTBEAT_RESPONSE_COMMAND = 0x02;

    public const PING = 0x03;
    public const PONG = 0x04;

    public const PREPARE_ACK = 0x05;
    public const COMMIT_ACK = 0x06;

    public const SLAVE_ASSIGNMENT = 0x07;
    public const CLONE_ASSIGNMENT = 0x08;

    public const SUBSCRIBE_REPLICA = 0x10;
    public const REPLICA_LOG_POSITION_ACK = 0x11;
    public const CREATE_CHUNK = 0x12;
    public const RAW_CHUNK_BULKD = 0x13;
    public const DATA_CHUNK_BULK = 0x14;
    public const REPLICA_SUBSCRIPTION_RETRY = 0x15;
    public const REPLICA_SUBSCRIBED = 0x16;

    public const WRITE_EVENTS = 0x82;
    public const WRITE_EVENTS_COMPLETED = 0x83;

    public const TRANSACTION_START = 0x84;
    public const TRANSACTION_START_COMPLETED = 0x85;
    public const TRANSACTION_WRITE = 0x86;
    public const TRANSACTION_WRITE_COMPLETED = 0x87;
    public const TRANSACTION_COMMIT = 0x88;
    public const TRANSACTION_COMMIT_COMPLETED = 0x89;

    public const DELETE_STREAM = 0x8A;
    public const DELETE_STREAM_COMPLETED = 0x8B;

    public const READ_EVENT = 0xB0;
    public const READ_EVENT_COMPLETED = 0xB1;
    public const READ_STREAM_EVENTS_FORWARD = 0xB2;
    public const READ_STREAM_EVENTS_FORWARD_COMPLETED = 0xB3;
    public const READ_STREAM_EVENTS_BACKWARD = 0xB4;
    public const READ_STREAM_EVENTS_BACKWARD_COMPLETED = 0xB5;
    public const REAL_ADD_EVENTS_FORWARD = 0xB6;
    public const REAL_ADD_EVENTS_FORWARD_COMPLETED = 0xB7;
    public const REAL_ADD_EVENTS_BACKWARD = 0xB8;
    public const REAL_ADD_EVENTS_BACKWARD_COMPLETED = 0xB9;

    public const SUBSCRIBE_TO_STREAM = 0xC0;
    public const SUBSCRIPTION_CONFIRMATION = 0xC1;
    public const STREAM_EVENT_APPEARED = 0xC2;
    public const UNSUBSCRIBE_FROM_STREAM = 0xC3;
    public const SUBSCRIPTION_DROPPED = 0xC4;
    public const CONNECT_TO_PERSISTENT_SUBSCRIPTION = 0xC5;
    public const PERSISTENT_SUBSCRIPTION_CONFIRMATION = 0xC6;
    public const PERSISTENT_SUBSCRIPTION_EVENT_APPEARED = 0xC7;
    public const CREATE_PERSISTENT_SUBSCRIPTION = 0xC8;
    public const CREATE_PERSISTENT_SUBSCRIPTION_COMPLETED = 0xC9;
    public const DELETE_PERSISTENT_SUBSCRIPTION = 0xCA;
    public const DELETE_PERSISTENT_SUBSCRIPTION_COMPLETED = 0xCB;
    public const PERSISTENT_SUBSCRIPTION_ACK_EVENTS = 0xCC;
    public const PERSISTENT_SUBSCRIPTION_NACK_EVENTS = 0xCD;
    public const UPDATE_PERSISTENT_SUBSCRIPTION = 0xCE;
    public const UPDATE_PERSISTENT_SUBSCRIPTION_COMPLETED = 0xCF;

    public const SCAVANGE_DATABASE = 0xD0;
    public const SCAVANGE_DATABASE_COMPLETED = 0xD1;

    public const BAD_REQUEST = 0xF0;
    public const NOT_HANDLED = 0xF1;
    public const AUTHENTICATE = 0xF2;
    public const AUTHENTICATED = 0xF3;
    public const NOT_AUTHENTICATED_EXCEPTION = 0xF4;
    public const IDENITFY_CLIENT = 0xF5;
    public const CLIENT_IDENTIFIED = 0xF6;

    private string $name;
    private int $value;

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

    public static function fromValue(int $value): self
    {
        foreach (self::OPTIONS as $name => $v) {
            if ($v === $value) {
                return self::{$name}();
            }
        }

        throw new InvalidArgumentException('Unknown enum value given');
    }

    public function equals(TcpCommand $other): bool
    {
        return $this->name === $other->name;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function value(): int
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
