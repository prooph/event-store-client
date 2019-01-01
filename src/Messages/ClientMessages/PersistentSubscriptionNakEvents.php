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
 * PersistentSubscriptionNakEvents message
 */
class PersistentSubscriptionNakEvents extends \ProtobufMessage
{
    /* Field index constants */
    const SUBSCRIPTION_ID = 1;
    const PROCESSED_EVENT_IDS = 2;
    const MESSAGE = 3;
    const ACTION = 4;

    /* @var array Field descriptors */
    protected static $fields = [
        self::SUBSCRIPTION_ID => [
            'name' => 'subscription_id',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_STRING,
        ],
        self::PROCESSED_EVENT_IDS => [
            'name' => 'processed_event_ids',
            'repeated' => true,
            'type' => \ProtobufMessage::PB_TYPE_STRING,
        ],
        self::MESSAGE => [
            'name' => 'message',
            'required' => false,
            'type' => \ProtobufMessage::PB_TYPE_STRING,
        ],
        self::ACTION => [
            'default' => \Prooph\EventStoreClient\Messages\ClientMessages\PersistentSubscriptionNakEvents_NakAction::Unknown,
            'name' => 'action',
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
        $this->values[self::PROCESSED_EVENT_IDS] = [];
        $this->values[self::MESSAGE] = null;
        $this->values[self::ACTION] = self::$fields[self::ACTION]['default'];
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
     * Appends value to 'processed_event_ids' list
     *
     * @param string $value Value to append
     *
     * @return null
     */
    public function appendProcessedEventIds($value)
    {
        return $this->append(self::PROCESSED_EVENT_IDS, $value);
    }

    /**
     * Clears 'processed_event_ids' list
     *
     * @return null
     */
    public function clearProcessedEventIds()
    {
        return $this->clear(self::PROCESSED_EVENT_IDS);
    }

    /**
     * Returns 'processed_event_ids' list
     *
     * @return string[]
     */
    public function getProcessedEventIds()
    {
        return $this->get(self::PROCESSED_EVENT_IDS);
    }

    /**
     * Returns 'processed_event_ids' iterator
     *
     * @return \ArrayIterator
     */
    public function getProcessedEventIdsIterator()
    {
        return new \ArrayIterator($this->get(self::PROCESSED_EVENT_IDS));
    }

    /**
     * Returns element from 'processed_event_ids' list at given offset
     *
     * @param int $offset Position in list
     *
     * @return string
     */
    public function getProcessedEventIdsAt($offset)
    {
        return $this->get(self::PROCESSED_EVENT_IDS, $offset);
    }

    /**
     * Returns count of 'processed_event_ids' list
     *
     * @return int
     */
    public function getProcessedEventIdsCount()
    {
        return $this->count(self::PROCESSED_EVENT_IDS);
    }

    /**
     * Sets value of 'message' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setMessage($value)
    {
        return $this->set(self::MESSAGE, $value);
    }

    /**
     * Returns value of 'message' property
     *
     * @return string
     */
    public function getMessage()
    {
        $value = $this->get(self::MESSAGE);

        return $value === null ? (string) $value : $value;
    }

    /**
     * Sets value of 'action' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setAction($value)
    {
        return $this->set(self::ACTION, $value);
    }

    /**
     * Returns value of 'action' property
     *
     * @return integer
     */
    public function getAction()
    {
        $value = $this->get(self::ACTION);

        return $value === null ? (int) $value : $value;
    }
}
}
