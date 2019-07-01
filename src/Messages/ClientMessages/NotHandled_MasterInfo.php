<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2019 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2019 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);
/**
 * Auto generated from ClientMessageDtos.proto at 2018-08-13 09:37:00
 *
 * Prooph.EventStoreClient.Messages.ClientMessages package
 */

namespace Prooph\EventStoreClient\Messages\ClientMessages {
/**
 * MasterInfo message embedded in NotHandled message
 */
class NotHandled_MasterInfo extends \ProtobufMessage
{
    /* Field index constants */
    const EXTERNAL_TCP_ADDRESS = 1;
    const EXTERNAL_TCP_PORT = 2;
    const EXTERNAL_HTTP_ADDRESS = 3;
    const EXTERNAL_HTTP_PORT = 4;
    const EXTERNAL_SECURE_TCP_ADDRESS = 5;
    const EXTERNAL_SECURE_TCP_PORT = 6;

    /* @var array Field descriptors */
    protected static $fields = [
        self::EXTERNAL_TCP_ADDRESS => [
            'name' => 'external_tcp_address',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_STRING,
        ],
        self::EXTERNAL_TCP_PORT => [
            'name' => 'external_tcp_port',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::EXTERNAL_HTTP_ADDRESS => [
            'name' => 'external_http_address',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_STRING,
        ],
        self::EXTERNAL_HTTP_PORT => [
            'name' => 'external_http_port',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::EXTERNAL_SECURE_TCP_ADDRESS => [
            'name' => 'external_secure_tcp_address',
            'required' => false,
            'type' => \ProtobufMessage::PB_TYPE_STRING,
        ],
        self::EXTERNAL_SECURE_TCP_PORT => [
            'name' => 'external_secure_tcp_port',
            'required' => false,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
    ];

    /**
     * Constructs new message container and clears its internal state
     */
    public function __construct()
    {
        $this->reset();
    }

    /**
     * Clears message values and sets default ones
     *
     * @return null
     */
    public function reset()
    {
        $this->values[self::EXTERNAL_TCP_ADDRESS] = null;
        $this->values[self::EXTERNAL_TCP_PORT] = null;
        $this->values[self::EXTERNAL_HTTP_ADDRESS] = null;
        $this->values[self::EXTERNAL_HTTP_PORT] = null;
        $this->values[self::EXTERNAL_SECURE_TCP_ADDRESS] = null;
        $this->values[self::EXTERNAL_SECURE_TCP_PORT] = null;
    }

    /**
     * Returns field descriptors
     *
     * @return array
     */
    public function fields()
    {
        return self::$fields;
    }

    /**
     * Sets value of 'external_tcp_address' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setExternalTcpAddress($value)
    {
        return $this->set(self::EXTERNAL_TCP_ADDRESS, $value);
    }

    /**
     * Returns value of 'external_tcp_address' property
     *
     * @return string
     */
    public function getExternalTcpAddress()
    {
        $value = $this->get(self::EXTERNAL_TCP_ADDRESS);

        return $value === null ? (string) $value : $value;
    }

    /**
     * Sets value of 'external_tcp_port' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setExternalTcpPort($value)
    {
        return $this->set(self::EXTERNAL_TCP_PORT, $value);
    }

    /**
     * Returns value of 'external_tcp_port' property
     *
     * @return integer
     */
    public function getExternalTcpPort()
    {
        $value = $this->get(self::EXTERNAL_TCP_PORT);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'external_http_address' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setExternalHttpAddress($value)
    {
        return $this->set(self::EXTERNAL_HTTP_ADDRESS, $value);
    }

    /**
     * Returns value of 'external_http_address' property
     *
     * @return string
     */
    public function getExternalHttpAddress()
    {
        $value = $this->get(self::EXTERNAL_HTTP_ADDRESS);

        return $value === null ? (string) $value : $value;
    }

    /**
     * Sets value of 'external_http_port' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setExternalHttpPort($value)
    {
        return $this->set(self::EXTERNAL_HTTP_PORT, $value);
    }

    /**
     * Returns value of 'external_http_port' property
     *
     * @return integer
     */
    public function getExternalHttpPort()
    {
        $value = $this->get(self::EXTERNAL_HTTP_PORT);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'external_secure_tcp_address' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setExternalSecureTcpAddress($value)
    {
        return $this->set(self::EXTERNAL_SECURE_TCP_ADDRESS, $value);
    }

    /**
     * Returns value of 'external_secure_tcp_address' property
     *
     * @return string
     */
    public function getExternalSecureTcpAddress()
    {
        $value = $this->get(self::EXTERNAL_SECURE_TCP_ADDRESS);

        return $value === null ? (string) $value : $value;
    }

    /**
     * Sets value of 'external_secure_tcp_port' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setExternalSecureTcpPort($value)
    {
        return $this->set(self::EXTERNAL_SECURE_TCP_PORT, $value);
    }

    /**
     * Returns value of 'external_secure_tcp_port' property
     *
     * @return integer
     */
    public function getExternalSecureTcpPort()
    {
        $value = $this->get(self::EXTERNAL_SECURE_TCP_PORT);

        return $value === null ? (int) $value : $value;
    }
}
}
