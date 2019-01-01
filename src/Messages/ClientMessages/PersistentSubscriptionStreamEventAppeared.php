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
 * PersistentSubscriptionStreamEventAppeared message
 */
class PersistentSubscriptionStreamEventAppeared extends \ProtobufMessage
{
    /* Field index constants */
    const EVENT = 1;
    const RETRYCOUNT = 2;

    /* @var array Field descriptors */
    protected static $fields = [
        self::EVENT => [
            'name' => 'event',
            'required' => true,
            'type' => '\Prooph\EventStoreClient\Messages\ClientMessages\ResolvedIndexedEvent',
        ],
        self::RETRYCOUNT => [
            'name' => 'retryCount',
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
        $this->values[self::EVENT] = null;
        $this->values[self::RETRYCOUNT] = null;
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
     * Sets value of 'event' property
     *
     * @param \Prooph\EventStoreClient\Messages\ClientMessages\ResolvedIndexedEvent $value Property value
     *
     * @return null
     */
    public function setEvent(\Prooph\EventStoreClient\Messages\ClientMessages\ResolvedIndexedEvent $value = null)
    {
        return $this->set(self::EVENT, $value);
    }

    /**
     * Returns value of 'event' property
     *
     * @return \Prooph\EventStoreClient\Messages\ClientMessages\ResolvedIndexedEvent
     */
    public function getEvent()
    {
        return $this->get(self::EVENT);
    }

    /**
     * Sets value of 'retryCount' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setRetryCount($value)
    {
        return $this->set(self::RETRYCOUNT, $value);
    }

    /**
     * Returns value of 'retryCount' property
     *
     * @return integer
     */
    public function getRetryCount()
    {
        $value = $this->get(self::RETRYCOUNT);

        return $value === null ? (int) $value : $value;
    }
}
}
