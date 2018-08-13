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
 * PersistentSubscriptionAckEvents message
 */
class PersistentSubscriptionAckEvents extends \ProtobufMessage
{
    /* Field index constants */
    const SUBSCRIPTION_ID = 1;
    const PROCESSED_EVENT_IDS = 2;

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
}
}
