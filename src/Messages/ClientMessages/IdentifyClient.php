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
 * IdentifyClient message
 */
class IdentifyClient extends \ProtobufMessage
{
    /* Field index constants */
    const VERSION = 1;
    const CONNECTION_NAME = 2;

    /* @var array Field descriptors */
    protected static $fields = [
        self::VERSION => [
            'name' => 'version',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::CONNECTION_NAME => [
            'name' => 'connection_name',
            'required' => false,
            'type' => \ProtobufMessage::PB_TYPE_STRING,
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
        $this->values[self::VERSION] = null;
        $this->values[self::CONNECTION_NAME] = null;
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
     * Sets value of 'version' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setVersion($value)
    {
        return $this->set(self::VERSION, $value);
    }

    /**
     * Returns value of 'version' property
     *
     * @return integer
     */
    public function getVersion()
    {
        $value = $this->get(self::VERSION);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'connection_name' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setConnectionName($value)
    {
        return $this->set(self::CONNECTION_NAME, $value);
    }

    /**
     * Returns value of 'connection_name' property
     *
     * @return string
     */
    public function getConnectionName()
    {
        $value = $this->get(self::CONNECTION_NAME);

        return $value === null ? (string) $value : $value;
    }
}
}
