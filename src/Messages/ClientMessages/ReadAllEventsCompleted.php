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
 * ReadAllEventsCompleted message
 */
class ReadAllEventsCompleted extends \ProtobufMessage
{
    /* Field index constants */
    const COMMIT_POSITION = 1;
    const PREPARE_POSITION = 2;
    const EVENTS = 3;
    const NEXT_COMMIT_POSITION = 4;
    const NEXT_PREPARE_POSITION = 5;
    const RESULT = 6;
    const ERROR = 7;

    /* @var array Field descriptors */
    protected static $fields = [
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
        self::EVENTS => [
            'name' => 'events',
            'repeated' => true,
            'type' => '\Prooph\EventStoreClient\Messages\ClientMessages\ResolvedEvent',
        ],
        self::NEXT_COMMIT_POSITION => [
            'name' => 'next_commit_position',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::NEXT_PREPARE_POSITION => [
            'name' => 'next_prepare_position',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::RESULT => [
            'default' => \Prooph\EventStoreClient\Messages\ClientMessages\ReadAllEventsCompleted_ReadAllResult::Success,
            'name' => 'result',
            'required' => false,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::ERROR => [
            'name' => 'error',
            'required' => false,
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
        $this->values[self::COMMIT_POSITION] = null;
        $this->values[self::PREPARE_POSITION] = null;
        $this->values[self::EVENTS] = [];
        $this->values[self::NEXT_COMMIT_POSITION] = null;
        $this->values[self::NEXT_PREPARE_POSITION] = null;
        $this->values[self::RESULT] = self::$fields[self::RESULT]['default'];
        $this->values[self::ERROR] = null;
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

    /**
     * Appends value to 'events' list
     *
     * @param \Prooph\EventStoreClient\Messages\ClientMessages\ResolvedEvent $value Value to append
     *
     * @return null
     */
    public function appendEvents(\Prooph\EventStoreClient\Messages\ClientMessages\ResolvedEvent $value)
    {
        return $this->append(self::EVENTS, $value);
    }

    /**
     * Clears 'events' list
     *
     * @return null
     */
    public function clearEvents()
    {
        return $this->clear(self::EVENTS);
    }

    /**
     * Returns 'events' list
     *
     * @return \Prooph\EventStoreClient\Messages\ClientMessages\ResolvedEvent[]
     */
    public function getEvents()
    {
        return $this->get(self::EVENTS);
    }

    /**
     * Returns 'events' iterator
     *
     * @return \ArrayIterator
     */
    public function getEventsIterator()
    {
        return new \ArrayIterator($this->get(self::EVENTS));
    }

    /**
     * Returns element from 'events' list at given offset
     *
     * @param int $offset Position in list
     *
     * @return \Prooph\EventStoreClient\Messages\ClientMessages\ResolvedEvent
     */
    public function getEventsAt($offset)
    {
        return $this->get(self::EVENTS, $offset);
    }

    /**
     * Returns count of 'events' list
     *
     * @return int
     */
    public function getEventsCount()
    {
        return $this->count(self::EVENTS);
    }

    /**
     * Sets value of 'next_commit_position' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setNextCommitPosition($value)
    {
        return $this->set(self::NEXT_COMMIT_POSITION, $value);
    }

    /**
     * Returns value of 'next_commit_position' property
     *
     * @return integer
     */
    public function getNextCommitPosition()
    {
        $value = $this->get(self::NEXT_COMMIT_POSITION);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'next_prepare_position' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setNextPreparePosition($value)
    {
        return $this->set(self::NEXT_PREPARE_POSITION, $value);
    }

    /**
     * Returns value of 'next_prepare_position' property
     *
     * @return integer
     */
    public function getNextPreparePosition()
    {
        $value = $this->get(self::NEXT_PREPARE_POSITION);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'result' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setResult($value)
    {
        return $this->set(self::RESULT, $value);
    }

    /**
     * Returns value of 'result' property
     *
     * @return integer
     */
    public function getResult()
    {
        $value = $this->get(self::RESULT);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'error' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setError($value)
    {
        return $this->set(self::ERROR, $value);
    }

    /**
     * Returns value of 'error' property
     *
     * @return string
     */
    public function getError()
    {
        $value = $this->get(self::ERROR);

        return $value === null ? (string) $value : $value;
    }
}
}
