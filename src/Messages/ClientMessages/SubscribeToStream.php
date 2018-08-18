<?php
/**
 * This file is part of the prooph/event-store-client.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
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
 * SubscribeToStream message
 */
class SubscribeToStream extends \ProtobufMessage
{
    /* Field index constants */
    const EVENT_STREAM_ID = 1;
    const RESOLVE_LINK_TOS = 2;

    /* @var array Field descriptors */
    protected static $fields = [
        self::EVENT_STREAM_ID => [
            'name' => 'event_stream_id',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_STRING,
        ],
        self::RESOLVE_LINK_TOS => [
            'name' => 'resolve_link_tos',
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
        $this->values[self::RESOLVE_LINK_TOS] = null;
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
}
}
