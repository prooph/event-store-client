<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: ClientMessageDtos.proto

namespace Prooph\EventStoreClient\Messages\ClientMessages\NotHandled;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 * Generated from protobuf message <code>Prooph.EventStoreClient.Messages.ClientMessages.NotHandled.MasterInfo</code>
 */
class MasterInfo extends \Google\Protobuf\Internal\Message
{
    /**
     * Generated from protobuf field <code>string external_tcp_address = 1;</code>
     */
    protected $external_tcp_address = '';
    /**
     * Generated from protobuf field <code>int32 external_tcp_port = 2;</code>
     */
    protected $external_tcp_port = 0;
    /**
     * Generated from protobuf field <code>string external_http_address = 3;</code>
     */
    protected $external_http_address = '';
    /**
     * Generated from protobuf field <code>int32 external_http_port = 4;</code>
     */
    protected $external_http_port = 0;
    /**
     * Generated from protobuf field <code>string external_secure_tcp_address = 5;</code>
     */
    protected $external_secure_tcp_address = '';
    /**
     * Generated from protobuf field <code>int32 external_secure_tcp_port = 6;</code>
     */
    protected $external_secure_tcp_port = 0;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $external_tcp_address
     *     @type int $external_tcp_port
     *     @type string $external_http_address
     *     @type int $external_http_port
     *     @type string $external_secure_tcp_address
     *     @type int $external_secure_tcp_port
     * }
     */
    public function __construct($data = NULL) {
        \GPBMetadata\ClientMessageDtos::initOnce();
        parent::__construct($data);
    }

    /**
     * Generated from protobuf field <code>string external_tcp_address = 1;</code>
     * @return string
     */
    public function getExternalTcpAddress()
    {
        return $this->external_tcp_address;
    }

    /**
     * Generated from protobuf field <code>string external_tcp_address = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setExternalTcpAddress($var)
    {
        GPBUtil::checkString($var, True);
        $this->external_tcp_address = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int32 external_tcp_port = 2;</code>
     * @return int
     */
    public function getExternalTcpPort()
    {
        return $this->external_tcp_port;
    }

    /**
     * Generated from protobuf field <code>int32 external_tcp_port = 2;</code>
     * @param int $var
     * @return $this
     */
    public function setExternalTcpPort($var)
    {
        GPBUtil::checkInt32($var);
        $this->external_tcp_port = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>string external_http_address = 3;</code>
     * @return string
     */
    public function getExternalHttpAddress()
    {
        return $this->external_http_address;
    }

    /**
     * Generated from protobuf field <code>string external_http_address = 3;</code>
     * @param string $var
     * @return $this
     */
    public function setExternalHttpAddress($var)
    {
        GPBUtil::checkString($var, True);
        $this->external_http_address = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int32 external_http_port = 4;</code>
     * @return int
     */
    public function getExternalHttpPort()
    {
        return $this->external_http_port;
    }

    /**
     * Generated from protobuf field <code>int32 external_http_port = 4;</code>
     * @param int $var
     * @return $this
     */
    public function setExternalHttpPort($var)
    {
        GPBUtil::checkInt32($var);
        $this->external_http_port = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>string external_secure_tcp_address = 5;</code>
     * @return string
     */
    public function getExternalSecureTcpAddress()
    {
        return $this->external_secure_tcp_address;
    }

    /**
     * Generated from protobuf field <code>string external_secure_tcp_address = 5;</code>
     * @param string $var
     * @return $this
     */
    public function setExternalSecureTcpAddress($var)
    {
        GPBUtil::checkString($var, True);
        $this->external_secure_tcp_address = $var;

        return $this;
    }

    /**
     * Generated from protobuf field <code>int32 external_secure_tcp_port = 6;</code>
     * @return int
     */
    public function getExternalSecureTcpPort()
    {
        return $this->external_secure_tcp_port;
    }

    /**
     * Generated from protobuf field <code>int32 external_secure_tcp_port = 6;</code>
     * @param int $var
     * @return $this
     */
    public function setExternalSecureTcpPort($var)
    {
        GPBUtil::checkInt32($var);
        $this->external_secure_tcp_port = $var;

        return $this;
    }

}

// Adding a class alias for backwards compatibility with the previous class name.
class_alias(MasterInfo::class, \Prooph\EventStoreClient\Messages\ClientMessages\NotHandled_MasterInfo::class);

