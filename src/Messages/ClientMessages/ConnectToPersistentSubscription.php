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
 * ConnectToPersistentSubscription message
 */
class ConnectToPersistentSubscription extends \ProtobufMessage
{
    /* Field index constants */
    const SUBSCRIPTION_ID = 1;
    const EVENT_STREAM_ID = 2;
    const ALLOWED_IN_FLIGHT_MESSAGES = 3;

    /* @var array Field descriptors */
    protected static $fields = [
        self::SUBSCRIPTION_ID => [
            'name' => 'subscription_id',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_STRING,
        ],
        self::EVENT_STREAM_ID => [
            'name' => 'event_stream_id',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_STRING,
        ],
        self::ALLOWED_IN_FLIGHT_MESSAGES => [
            'name' => 'allowed_in_flight_messages',
            'required' => true,
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
        $this->values[self::SUBSCRIPTION_ID] = null;
        $this->values[self::EVENT_STREAM_ID] = null;
        $this->values[self::ALLOWED_IN_FLIGHT_MESSAGES] = null;
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
     * Sets value of 'subscription_id' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setSubscriptionId($value)
    {
        return $this->set(self::SUBSCRIPTION_ID, $value);
    }

    /**
     * Returns value of 'subscription_id' property
     *
     * @return string
     */
    public function getSubscriptionId()
    {
        $value = $this->get(self::SUBSCRIPTION_ID);

        return $value === null ? (string) $value : $value;
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
     * Sets value of 'allowed_in_flight_messages' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setAllowedInFlightMessages($value)
    {
        return $this->set(self::ALLOWED_IN_FLIGHT_MESSAGES, $value);
    }

    /**
     * Returns value of 'allowed_in_flight_messages' property
     *
     * @return integer
     */
    public function getAllowedInFlightMessages()
    {
        $value = $this->get(self::ALLOWED_IN_FLIGHT_MESSAGES);

        return $value === null ? (int) $value : $value;
    }
}
}
