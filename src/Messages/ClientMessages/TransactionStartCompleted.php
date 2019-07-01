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
 * TransactionStartCompleted message
 */
class TransactionStartCompleted extends \ProtobufMessage
{
    /* Field index constants */
    const TRANSACTION_ID = 1;
    const RESULT = 2;
    const MESSAGE = 3;

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
}
}
