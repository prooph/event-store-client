<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
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
 * TransactionStart message
 */
class TransactionStart extends \ProtobufMessage
{
    /* Field index constants */
    const EVENT_STREAM_ID = 1;
    const EXPECTED_VERSION = 2;
    const REQUIRE_MASTER = 3;

    /* @var array Field descriptors */
    protected static $fields = [
        self::EVENT_STREAM_ID => [
            'name' => 'event_stream_id',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_STRING,
        ],
        self::EXPECTED_VERSION => [
            'name' => 'expected_version',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::REQUIRE_MASTER => [
            'name' => 'require_master',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_BOOL,
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
        $this->values[self::EVENT_STREAM_ID] = null;
        $this->values[self::EXPECTED_VERSION] = null;
        $this->values[self::REQUIRE_MASTER] = null;
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
     * Sets value of 'event_stream_id' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setEventStreamId($value)
    {
        return $this->set(self::EVENT_STREAM_ID, $value);
    }

    /**
     * Returns value of 'event_stream_id' property
     *
     * @return string
     */
    public function getEventStreamId()
    {
        $value = $this->get(self::EVENT_STREAM_ID);

        return $value === null ? (string) $value : $value;
    }

    /**
     * Sets value of 'expected_version' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setExpectedVersion($value)
    {
        return $this->set(self::EXPECTED_VERSION, $value);
    }

    /**
     * Returns value of 'expected_version' property
     *
     * @return integer
     */
    public function getExpectedVersion()
    {
        $value = $this->get(self::EXPECTED_VERSION);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'require_master' property
     *
     * @param boolean $value Property value
     *
     * @return null
     */
    public function setRequireMaster($value)
    {
        return $this->set(self::REQUIRE_MASTER, $value);
    }

    /**
     * Returns value of 'require_master' property
     *
     * @return boolean
     */
    public function getRequireMaster()
    {
        $value = $this->get(self::REQUIRE_MASTER);

        return $value === null ? (bool) $value : $value;
    }
}
}
