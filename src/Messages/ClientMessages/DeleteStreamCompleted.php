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
 * DeleteStreamCompleted message
 */
class DeleteStreamCompleted extends \ProtobufMessage
{
    /* Field index constants */
    const RESULT = 1;
    const MESSAGE = 2;
    const PREPARE_POSITION = 3;
    const COMMIT_POSITION = 4;

    /* @var array Field descriptors */
    protected static $fields = [
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
        $this->values[self::RESULT] = null;
        $this->values[self::MESSAGE] = null;
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
