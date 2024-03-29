<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: ClientMessageDtos.proto

namespace Prooph\EventStoreClient\Messages\ClientMessages\CreatePersistentSubscriptionCompleted;

use UnexpectedValueException;

/**
 * Protobuf type <code>Prooph.EventStoreClient.Messages.ClientMessages.CreatePersistentSubscriptionCompleted.CreatePersistentSubscriptionResult</code>
 */
class CreatePersistentSubscriptionResult
{
    /**
     * Generated from protobuf enum <code>Success = 0;</code>
     */
    const Success = 0;
    /**
     * Generated from protobuf enum <code>AlreadyExists = 1;</code>
     */
    const AlreadyExists = 1;
    /**
     * Generated from protobuf enum <code>Fail = 2;</code>
     */
    const Fail = 2;
    /**
     * Generated from protobuf enum <code>AccessDenied = 3;</code>
     */
    const AccessDenied = 3;

    private static $valueToName = [
        self::Success => 'Success',
        self::AlreadyExists => 'AlreadyExists',
        self::Fail => 'Fail',
        self::AccessDenied => 'AccessDenied',
    ];

    public static function name($value)
    {
        if (!isset(self::$valueToName[$value])) {
            throw new UnexpectedValueException(sprintf(
                    'Enum %s has no name defined for value %s', __CLASS__, $value));
        }
        return self::$valueToName[$value];
    }


    public static function value($name)
    {
        $const = __CLASS__ . '::' . strtoupper($name);
        if (!defined($const)) {
            throw new UnexpectedValueException(sprintf(
                    'Enum %s has no value defined for name %s', __CLASS__, $name));
        }
        return constant($const);
    }
}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(CreatePersistentSubscriptionResult::class, \Prooph\EventStoreClient\Messages\ClientMessages\CreatePersistentSubscriptionCompleted_CreatePersistentSubscriptionResult::class);

