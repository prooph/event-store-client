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
 * PersistentSubscriptionConfirmation message
 */
class PersistentSubscriptionConfirmation extends \ProtobufMessage
{
    /* Field index constants */
    const LAST_COMMIT_POSITION = 1;
    const SUBSCRIPTION_ID = 2;
    const LAST_EVENT_NUMBER = 3;

    /* @var array Field descriptors */
    protected static $fields = [
        self::LAST_COMMIT_POSITION => [
            'name' => 'last_commit_position',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::SUBSCRIPTION_ID => [
            'name' => 'subscription_id',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_STRING,
        ],
        self::LAST_EVENT_NUMBER => [
            'name' => 'last_event_number',
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
        $this->values[self::LAST_COMMIT_POSITION] = null;
        $this->values[self::SUBSCRIPTION_ID] = null;
        $this->values[self::LAST_EVENT_NUMBER] = null;
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
     * Sets value of 'last_commit_position' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setLastCommitPosition($value)
    {
        return $this->set(self::LAST_COMMIT_POSITION, $value);
    }

    /**
     * Returns value of 'last_commit_position' property
     *
     * @return integer
     */
    public function getLastCommitPosition()
    {
        $value = $this->get(self::LAST_COMMIT_POSITION);

        return $value === null ? (int) $value : $value;
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
     * Sets value of 'last_event_number' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setLastEventNumber($value)
    {
        return $this->set(self::LAST_EVENT_NUMBER, $value);
    }

    /**
     * Returns value of 'last_event_number' property
     *
     * @return integer
     */
    public function getLastEventNumber()
    {
        $value = $this->get(self::LAST_EVENT_NUMBER);

        return $value === null ? (int) $value : $value;
    }
}
}
