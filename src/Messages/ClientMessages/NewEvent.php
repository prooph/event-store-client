<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: ClientMessageDtos.proto

namespace Prooph\EventStoreClient\Messages\ClientMessages;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>Prooph.EventStoreClient.Messages.ClientMessages.NewEvent</code>
 */
class NewEvent extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>bytes event_id = 1;</code>
     */
    protected $event_id = '';
    /**
     * Generated from protobuf field <code>string event_type = 2;</code>
     */
    protected $event_type = '';
    /**
     * Generated from protobuf field <code>int32 data_content_type = 3;</code>
     */
    protected $data_content_type = 0;
    /**
     * Generated from protobuf field <code>int32 metadata_content_type = 4;</code>
     */
    protected $metadata_content_type = 0;
    /**
     * Generated from protobuf field <code>bytes data = 5;</code>
     */
    protected $data = '';
    /**
     * Generated from protobuf field <code>bytes metadata = 6;</code>
     */
    protected $metadata = '';

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $event_id
     *     @type string $event_type
     *     @type int $data_content_type
     *     @type int $metadata_content_type
     *     @type string $data
     *     @type string $metadata
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\ClientMessageDtos::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>bytes event_id = 1;</code>
     * @return string
     */
    public function getEventId()
    {
        return $this->event_id;
    }

    /**
     * Generated from protobuf field <code>bytes event_id = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setEventId($var)
    {
        GPBUtil::checkString($var, False);
        $this->event_id = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>string event_type = 2;</code>
     * @return string
     */
    public function getEventType()
    {
        return $this->event_type;
    }

    /**
     * Generated from protobuf field <code>string event_type = 2;</code>
     * @param string $var
     * @return $this
     */
    public function setEventType($var)
    {
        GPBUtil::checkString($var, True);
        $this->event_type = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int32 data_content_type = 3;</code>
     * @return int
     */
    public function getDataContentType()
    {
        return $this->data_content_type;
    }

    /**
     * Generated from protobuf field <code>int32 data_content_type = 3;</code>
     * @param int $var
     * @return $this
     */
    public function setDataContentType($var)
    {
        GPBUtil::checkInt32($var);
        $this->data_content_type = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int32 metadata_content_type = 4;</code>
     * @return int
     */
    public function getMetadataContentType()
    {
        return $this->metadata_content_type;
    }

    /**
     * Generated from protobuf field <code>int32 metadata_content_type = 4;</code>
     * @param int $var
     * @return $this
     */
    public function setMetadataContentType($var)
    {
        GPBUtil::checkInt32($var);
        $this->metadata_content_type = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>bytes data = 5;</code>
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Generated from protobuf field <code>bytes data = 5;</code>
     * @param string $var
     * @return $this
     */
    public function setData($var)
    {
        GPBUtil::checkString($var, False);
        $this->data = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>bytes metadata = 6;</code>
     * @return string
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * Generated from protobuf field <code>bytes metadata = 6;</code>
     * @param string $var
     * @return $this
     */
    public function setMetadata($var)
    {
        GPBUtil::checkString($var, False);
        $this->metadata = $var;

        return $this;
    }

}

