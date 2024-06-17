<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2024 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2024 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\SystemData;

enum TcpCommand: int
{
    case HeartbeatRequestCommand = 0x01;
    case HeartbeatResponseCommand = 0x02;
    case Ping = 0x03;
    case Pong = 0x04;
    case PrepareAck = 0x05;
    case CommitAck = 0x06;
    case SlaveAssignment = 0x07;
    case CloneAssignment = 0x08;
    case SubscribeReplica = 0x10;
    case ReplicaLogPositionAck = 0x11;
    case CreateChunk = 0x12;
    case RawChunkBulk = 0x13;
    case DataChunkBulk = 0x14;
    case ReplicaSubscriptionRetry = 0x15;
    case ReplicaSubscribed = 0x16;
    case WriteEvents = 0x82;
    case WriteEventsCompleted = 0x83;
    case TransactionStart = 0x84;
    case TransactionStartCompleted = 0x85;
    case TransactionWrite = 0x86;
    case TransactionWriteCompleted = 0x87;
    case TransactionCommit = 0x88;
    case TransactionCommitCompleted = 0x89;
    case DeleteStream = 0x8A;
    case DeleteStreamCompleted = 0x8B;
    case ReadEvent = 0xB0;
    case ReadEventCompleted = 0xB1;
    case ReadStreamEventsForward = 0xB2;
    case ReadStreamEventsForwardCompleted = 0xB3;
    case ReadStreamEventsBackward = 0xB4;
    case ReadStreamEventsBackwardCompleted = 0xB5;
    case ReadAllEventsForward = 0xB6;
    case ReadAllEventsForwardCompleted = 0xB7;
    case ReadAllEventsBackward = 0xB8;
    case ReadAllEventsBackwardCompleted = 0xB9;
    case SubscribeToStream = 0xC0;
    case SubscriptionConfirmation = 0xC1;
    case StreamEventAppeared = 0xC2;
    case UnsubscribeFromStream = 0xC3;
    case SubscriptionDropped = 0xC4;
    case ConnectToPersistentSubscription = 0xC5;
    case PersistentSubscriptionConfirmation = 0xC6;
    case PersistentSubscriptionStreamEventAppeared = 0xC7;
    case CreatePersistentSubscription = 0xC8;
    case CreatePersistentSubscriptionCompleted = 0xC9;
    case DeletePersistentSubscription = 0xCA;
    case DeletePersistentSubscriptionCompleted = 0xCB;
    case PersistentSubscriptionAckEvents = 0xCC;
    case PersistentSubscriptionNakEvents = 0xCD;
    case UpdatePersistentSubscription = 0xCE;
    case UpdatePersistentSubscriptionCompleted = 0xCF;
    case ScavengeDatabase = 0xD0;
    case ScavengeDatabaseCompleted = 0xD1;
    case BadRequest = 0xF0;
    case NotHandled = 0xF1;
    case Authenticate = 0xF2;
    case Authenticated = 0xF3;
    case NotAuthenticatedException = 0xF4;
    case IdentifyClient = 0xF5;
    case ClientIdentified = 0xF6;
}
