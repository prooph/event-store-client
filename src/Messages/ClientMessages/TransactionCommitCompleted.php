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
 * TransactionCommitCompleted message
 */
class TransactionCommitCompleted extends \ProtobufMessage
{
    /* Field index constants */
    const TRANSACTION_ID = 1;
    const RESULT = 2;
    const MESSAGE = 3;
    const FIRST_EVENT_NUMBER = 4;
    const LAST_EVENT_NUMBER = 5;
    const PREPARE_POSITION = 6;
    const COMMIT_POSITION = 7;

    /* @var array Field descriptors */
    protected static $fields = [
        self::TRANSACTION_ID => [
            'name' => 'transaction_id',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::RESULT => [
            'name' => 'result',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::MESSAGE => [
            'name' => 'message',
            'required' => false,
            'type' => \ProtobufMessage::PB_TYPE_STRING,
        ],
        self::FIRST_EVENT_NUMBER => [
            'name' => 'first_event_number',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::LAST_EVENT_NUMBER => [
            'name' => 'last_event_number',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::PREPARE_POSITION => [
            'name' => 'prepare_position',
            'required' => false,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::COMMIT_POSITION => [
            'name' => 'commit_position',
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
        $this->values[self::TRANSACTION_ID] = null;
        $this->values[self::RESULT] = null;
        $this->values[self::MESSAGE] = null;
        $this->values[self::FIRST_EVENT_NUMBER] = null;
        $this->values[self::LAST_EVENT_NUMBER] = null;
        $this->values[self::PREPARE_POSITION] = null;
        $this->values[self::COMMIT_POSITION] = null;
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
     * Sets value of 'transaction_id' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setTransactionId($value)
    {
        return $this->set(self::TRANSACTION_ID, $value);
    }

    /**
     * Returns value of 'transaction_id' property
     *
     * @return integer
     */
    public function getTransactionId()
    {
        $value = $this->get(self::TRANSACTION_ID);

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
     * Sets value of 'first_event_number' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setFirstEventNumber($value)
    {
        return $this->set(self::FIRST_EVENT_NUMBER, $value);
    }

    /**
     * Returns value of 'first_event_number' property
     *
     * @return integer
     */
    public function getFirstEventNumber()
    {
        $value = $this->get(self::FIRST_EVENT_NUMBER);

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
}
}
