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
 * StreamEventAppeared message
 */
class StreamEventAppeared extends \ProtobufMessage
{
    /* Field index constants */
    const EVENT = 1;

    /* @var array Field descriptors */
    protected static $fields = [
        self::EVENT => [
            'name' => 'event',
            'required' => true,
            'type' => '\Prooph\EventStoreClient\Messages\ClientMessages\ResolvedEvent',
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
     * @param \Prooph\EventStoreClient\Messages\ClientMessages\ResolvedEvent $value Property value
     *
     * @return null
     */
    public function setEvent(\Prooph\EventStoreClient\Messages\ClientMessages\ResolvedEvent $value = null)
    {
        return $this->set(self::EVENT, $value);
    }

    /**
     * Returns value of 'event' property
     *
     * @return \Prooph\EventStoreClient\Messages\ClientMessages\ResolvedEvent
     */
    public function getEvent()
    {
        return $this->get(self::EVENT);
    }
}
}
