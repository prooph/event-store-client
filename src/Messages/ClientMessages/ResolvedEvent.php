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
 * ResolvedEvent message
 */
class ResolvedEvent extends \ProtobufMessage
{
    /* Field index constants */
    const EVENT = 1;
    const LINK = 2;
    const COMMIT_POSITION = 3;
    const PREPARE_POSITION = 4;

    /* @var array Field descriptors */
    protected static $fields = [
        self::EVENT => [
            'name' => 'event',
            'required' => true,
            'type' => '\Prooph\EventStoreClient\Messages\ClientMessages\EventRecord',
        ],
        self::LINK => [
            'name' => 'link',
            'required' => false,
            'type' => '\Prooph\EventStoreClient\Messages\ClientMessages\EventRecord',
        ],
        self::COMMIT_POSITION => [
            'name' => 'commit_position',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::PREPARE_POSITION => [
            'name' => 'prepare_position',
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
        $this->values[self::EVENT] = null;
        $this->values[self::LINK] = null;
        $this->values[self::COMMIT_POSITION] = null;
        $this->values[self::PREPARE_POSITION] = null;
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
     * @param \Prooph\EventStoreClient\Messages\ClientMessages\EventRecord $value Property value
     *
     * @return null
     */
    public function setEvent(\Prooph\EventStoreClient\Messages\ClientMessages\EventRecord $value = null)
    {
        return $this->set(self::EVENT, $value);
    }

    /**
     * Returns value of 'event' property
     *
     * @return \Prooph\EventStoreClient\Messages\ClientMessages\EventRecord
     */
    public function getEvent()
    {
        return $this->get(self::EVENT);
    }

    /**
     * Sets value of 'link' property
     *
     * @param \Prooph\EventStoreClient\Messages\ClientMessages\EventRecord $value Property value
     *
     * @return null
     */
    public function setLink(\Prooph\EventStoreClient\Messages\ClientMessages\EventRecord $value = null)
    {
        return $this->set(self::LINK, $value);
    }

    /**
     * Returns value of 'link' property
     *
     * @return \Prooph\EventStoreClient\Messages\ClientMessages\EventRecord
     */
    public function getLink()
    {
        return $this->get(self::LINK);
    }

    /**
     * Sets value of 'commit_position' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setCommitPosition($value)
    {
        return $this->set(self::COMMIT_POSITION, $value);
    }

    /**
     * Returns value of 'commit_position' property
     *
     * @return integer
     */
    public function getCommitPosition()
    {
        $value = $this->get(self::COMMIT_POSITION);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'prepare_position' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setPreparePosition($value)
    {
        return $this->set(self::PREPARE_POSITION, $value);
    }

    /**
     * Returns value of 'prepare_position' property
     *
     * @return integer
     */
    public function getPreparePosition()
    {
        $value = $this->get(self::PREPARE_POSITION);

        return $value === null ? (int) $value : $value;
    }
}
}
