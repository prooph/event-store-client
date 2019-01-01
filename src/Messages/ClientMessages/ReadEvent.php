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
 * ReadEvent message
 */
class ReadEvent extends \ProtobufMessage
{
    /* Field index constants */
    const EVENT_STREAM_ID = 1;
    const EVENT_NUMBER = 2;
    const RESOLVE_LINK_TOS = 3;
    const REQUIRE_MASTER = 4;

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
        self::RESOLVE_LINK_TOS => [
            'name' => 'resolve_link_tos',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_BOOL,
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
        $this->values[self::EVENT_NUMBER] = null;
        $this->values[self::RESOLVE_LINK_TOS] = null;
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
     * Sets value of 'resolve_link_tos' property
     *
     * @param boolean $value Property value
     *
     * @return null
     */
    public function setResolveLinkTos($value)
    {
        return $this->set(self::RESOLVE_LINK_TOS, $value);
    }

    /**
     * Returns value of 'resolve_link_tos' property
     *
     * @return boolean
     */
    public function getResolveLinkTos()
    {
        $value = $this->get(self::RESOLVE_LINK_TOS);

        return $value === null ? (bool) $value : $value;
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
