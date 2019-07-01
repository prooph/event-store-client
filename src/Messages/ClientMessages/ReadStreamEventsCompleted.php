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
 * ReadStreamEventsCompleted message
 */
class ReadStreamEventsCompleted extends \ProtobufMessage
{
    /* Field index constants */
    const EVENTS = 1;
    const RESULT = 2;
    const NEXT_EVENT_NUMBER = 3;
    const LAST_EVENT_NUMBER = 4;
    const IS_END_OF_STREAM = 5;
    const LAST_COMMIT_POSITION = 6;
    const ERROR = 7;

    /* @var array Field descriptors */
    protected static $fields = [
        self::EVENTS => [
            'name' => 'events',
            'repeated' => true,
            'type' => '\Prooph\EventStoreClient\Messages\ClientMessages\ResolvedIndexedEvent',
        ],
        self::RESULT => [
            'name' => 'result',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::NEXT_EVENT_NUMBER => [
            'name' => 'next_event_number',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::LAST_EVENT_NUMBER => [
            'name' => 'last_event_number',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::IS_END_OF_STREAM => [
            'name' => 'is_end_of_stream',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_BOOL,
        ],
        self::LAST_COMMIT_POSITION => [
            'name' => 'last_commit_position',
            'required' => true,
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
        $this->values[self::EVENTS] = [];
        $this->values[self::RESULT] = null;
        $this->values[self::NEXT_EVENT_NUMBER] = null;
        $this->values[self::LAST_EVENT_NUMBER] = null;
        $this->values[self::IS_END_OF_STREAM] = null;
        $this->values[self::LAST_COMMIT_POSITION] = null;
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
     * Appends value to 'events' list
     *
     * @param \Prooph\EventStoreClient\Messages\ClientMessages\ResolvedIndexedEvent $value Value to append
     *
     * @return null
     */
    public function appendEvents(\Prooph\EventStoreClient\Messages\ClientMessages\ResolvedIndexedEvent $value)
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
     * @return \Prooph\EventStoreClient\Messages\ClientMessages\ResolvedIndexedEvent[]
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
     * @return \Prooph\EventStoreClient\Messages\ClientMessages\ResolvedIndexedEvent
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
     * Sets value of 'next_event_number' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setNextEventNumber($value)
    {
        return $this->set(self::NEXT_EVENT_NUMBER, $value);
    }

    /**
     * Returns value of 'next_event_number' property
     *
     * @return integer
     */
    public function getNextEventNumber()
    {
        $value = $this->get(self::NEXT_EVENT_NUMBER);

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

    /**
     * Sets value of 'is_end_of_stream' property
     *
     * @param boolean $value Property value
     *
     * @return null
     */
    public function setIsEndOfStream($value)
    {
        return $this->set(self::IS_END_OF_STREAM, $value);
    }

    /**
     * Returns value of 'is_end_of_stream' property
     *
     * @return boolean
     */
    public function getIsEndOfStream()
    {
        $value = $this->get(self::IS_END_OF_STREAM);

        return $value === null ? (bool) $value : $value;
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
