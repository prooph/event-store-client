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
 * SubscriptionConfirmation message
 */
class SubscriptionConfirmation extends \ProtobufMessage
{
    /* Field index constants */
    const LAST_COMMIT_POSITION = 1;
    const LAST_EVENT_NUMBER = 2;

    /* @var array Field descriptors */
    protected static $fields = [
        self::LAST_COMMIT_POSITION => [
            'name' => 'last_commit_position',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
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
