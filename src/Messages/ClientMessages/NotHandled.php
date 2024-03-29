<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: ClientMessageDtos.proto

namespace Prooph\EventStoreClient\Messages\ClientMessages;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>Prooph.EventStoreClient.Messages.ClientMessages.NotHandled</code>
 */
class NotHandled extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>.Prooph.EventStoreClient.Messages.ClientMessages.NotHandled.NotHandledReason reason = 1;</code>
     */
    protected $reason = 0;
    /**
     * Generated from protobuf field <code>bytes additional_info = 2;</code>
     */
    protected $additional_info = '';

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type int $reason
     *     @type string $additional_info
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\ClientMessageDtos::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>.Prooph.EventStoreClient.Messages.ClientMessages.NotHandled.NotHandledReason reason = 1;</code>
     * @return int
     */
    public function getReason()
    {
        return $this->reason;
    }

    /**
     * Generated from protobuf field <code>.Prooph.EventStoreClient.Messages.ClientMessages.NotHandled.NotHandledReason reason = 1;</code>
     * @param int $var
     * @return $this
     */
    public function setReason($var)
    {
        GPBUtil::checkEnum($var, \Prooph\EventStoreClient\Messages\ClientMessages\NotHandled\NotHandledReason::class);
        $this->reason = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>bytes additional_info = 2;</code>
     * @return string
     */
    public function getAdditionalInfo()
    {
        return $this->additional_info;
    }

    /**
     * Generated from protobuf field <code>bytes additional_info = 2;</code>
     * @param string $var
     * @return $this
     */
    public function setAdditionalInfo($var)
    {
        GPBUtil::checkString($var, False);
        $this->additional_info = $var;

        return $this;
    }

}

