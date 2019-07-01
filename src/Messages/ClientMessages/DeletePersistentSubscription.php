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
 * DeletePersistentSubscription message
 */
class DeletePersistentSubscription extends \ProtobufMessage
{
    /* Field index constants */
    const SUBSCRIPTION_GROUP_NAME = 1;
    const EVENT_STREAM_ID = 2;

    /* @var array Field descriptors */
    protected static $fields = [
        self::SUBSCRIPTION_GROUP_NAME => [
            'name' => 'subscription_group_name',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_STRING,
        ],
        self::EVENT_STREAM_ID => [
            'name' => 'event_stream_id',
            'required' => true,
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
        $this->values[self::SUBSCRIPTION_GROUP_NAME] = null;
        $this->values[self::EVENT_STREAM_ID] = null;
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
     * Sets value of 'subscription_group_name' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setSubscriptionGroupName($value)
    {
        return $this->set(self::SUBSCRIPTION_GROUP_NAME, $value);
    }

    /**
     * Returns value of 'subscription_group_name' property
     *
     * @return string
     */
    public function getSubscriptionGroupName()
    {
        $value = $this->get(self::SUBSCRIPTION_GROUP_NAME);

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
}
}
