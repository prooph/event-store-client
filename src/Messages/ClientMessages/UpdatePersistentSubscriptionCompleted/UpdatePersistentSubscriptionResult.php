<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: ClientMessageDtos.proto

namespace Prooph\EventStoreClient\Messages\ClientMessages\UpdatePersistentSubscriptionCompleted;

use UnexpectedValueException;

/**
 * Protobuf type <code>Prooph.EventStoreClient.Messages.ClientMessages.UpdatePersistentSubscriptionCompleted.UpdatePersistentSubscriptionResult</code>
 */
class UpdatePersistentSubscriptionResult
{
    /**
     * Generated from protobuf enum <code>Success = 0;</code>
     */
    const Success = 0;
    /**
     * Generated from protobuf enum <code>DoesNotExist = 1;</code>
     */
    const DoesNotExist = 1;
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
        self::DoesNotExist => 'DoesNotExist',
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
class_alias(UpdatePersistentSubscriptionResult::class, \Prooph\EventStoreClient\Messages\ClientMessages\UpdatePersistentSubscriptionCompleted_UpdatePersistentSubscriptionResult::class);

