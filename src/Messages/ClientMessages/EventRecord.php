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
 * EventRecord message
 */
class EventRecord extends \ProtobufMessage
{
    /* Field index constants */
    const EVENT_STREAM_ID = 1;
    const EVENT_NUMBER = 2;
    const EVENT_ID = 3;
    const EVENT_TYPE = 4;
    const DATA_CONTENT_TYPE = 5;
    const METADATA_CONTENT_TYPE = 6;
    const DATA = 7;
    const METADATA = 8;
    const CREATED = 9;
    const CREATED_EPOCH = 10;

    /* @var array Field descriptors */
    protected static $fields = [
        self::EVENT_STREAM_ID => [
            'name' => 'event_stream_id',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_STRING,
        ],
        self::EVENT_NUMBER => [
            'name' => 'event_number',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::EVENT_ID => [
            'name' => 'event_id',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_STRING,
        ],
        self::EVENT_TYPE => [
            'name' => 'event_type',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_STRING,
        ],
        self::DATA_CONTENT_TYPE => [
            'name' => 'data_content_type',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::METADATA_CONTENT_TYPE => [
            'name' => 'metadata_content_type',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::DATA => [
            'name' => 'data',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_STRING,
        ],
        self::METADATA => [
            'name' => 'metadata',
            'required' => false,
            'type' => \ProtobufMessage::PB_TYPE_STRING,
        ],
        self::CREATED => [
            'name' => 'created',
            'required' => false,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::CREATED_EPOCH => [
            'name' => 'created_epoch',
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
        $this->values[self::EVENT_STREAM_ID] = null;
        $this->values[self::EVENT_NUMBER] = null;
        $this->values[self::EVENT_ID] = null;
        $this->values[self::EVENT_TYPE] = null;
        $this->values[self::DATA_CONTENT_TYPE] = null;
        $this->values[self::METADATA_CONTENT_TYPE] = null;
        $this->values[self::DATA] = null;
        $this->values[self::METADATA] = null;
        $this->values[self::CREATED] = null;
        $this->values[self::CREATED_EPOCH] = null;
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
     * Sets value of 'event_number' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setEventNumber($value)
    {
        return $this->set(self::EVENT_NUMBER, $value);
    }

    /**
     * Returns value of 'event_number' property
     *
     * @return integer
     */
    public function getEventNumber()
    {
        $value = $this->get(self::EVENT_NUMBER);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'event_id' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setEventId($value)
    {
        return $this->set(self::EVENT_ID, $value);
    }

    /**
     * Returns value of 'event_id' property
     *
     * @return string
     */
    public function getEventId()
    {
        $value = $this->get(self::EVENT_ID);

        return $value === null ? (string) $value : $value;
    }

    /**
     * Sets value of 'event_type' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setEventType($value)
    {
        return $this->set(self::EVENT_TYPE, $value);
    }

    /**
     * Returns value of 'event_type' property
     *
     * @return string
     */
    public function getEventType()
    {
        $value = $this->get(self::EVENT_TYPE);

        return $value === null ? (string) $value : $value;
    }

    /**
     * Sets value of 'data_content_type' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setDataContentType($value)
    {
        return $this->set(self::DATA_CONTENT_TYPE, $value);
    }

    /**
     * Returns value of 'data_content_type' property
     *
     * @return integer
     */
    public function getDataContentType()
    {
        $value = $this->get(self::DATA_CONTENT_TYPE);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'metadata_content_type' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setMetadataContentType($value)
    {
        return $this->set(self::METADATA_CONTENT_TYPE, $value);
    }

    /**
     * Returns value of 'metadata_content_type' property
     *
     * @return integer
     */
    public function getMetadataContentType()
    {
        $value = $this->get(self::METADATA_CONTENT_TYPE);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'data' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setData($value)
    {
        return $this->set(self::DATA, $value);
    }

    /**
     * Returns value of 'data' property
     *
     * @return string
     */
    public function getData()
    {
        $value = $this->get(self::DATA);

        return $value === null ? (string) $value : $value;
    }

    /**
     * Sets value of 'metadata' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setMetadata($value)
    {
        return $this->set(self::METADATA, $value);
    }

    /**
     * Returns value of 'metadata' property
     *
     * @return string
     */
    public function getMetadata()
    {
        $value = $this->get(self::METADATA);

        return $value === null ? (string) $value : $value;
    }

    /**
     * Sets value of 'created' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setCreated($value)
    {
        return $this->set(self::CREATED, $value);
    }

    /**
     * Returns value of 'created' property
     *
     * @return integer
     */
    public function getCreated()
    {
        $value = $this->get(self::CREATED);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'created_epoch' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setCreatedEpoch($value)
    {
        return $this->set(self::CREATED_EPOCH, $value);
    }

    /**
     * Returns value of 'created_epoch' property
     *
     * @return integer
     */
    public function getCreatedEpoch()
    {
        $value = $this->get(self::CREATED_EPOCH);

        return $value === null ? (int) $value : $value;
    }
}
}
