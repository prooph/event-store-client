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
 * ScavengeDatabaseCompleted message
 */
class ScavengeDatabaseCompleted extends \ProtobufMessage
{
    /* Field index constants */
    const RESULT = 1;
    const ERROR = 2;
    const TOTAL_TIME_MS = 3;
    const TOTAL_SPACE_SAVED = 4;

    /* @var array Field descriptors */
    protected static $fields = [
        self::RESULT => [
            'name' => 'result',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::ERROR => [
            'name' => 'error',
            'required' => false,
            'type' => \ProtobufMessage::PB_TYPE_STRING,
        ],
        self::TOTAL_TIME_MS => [
            'name' => 'total_time_ms',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::TOTAL_SPACE_SAVED => [
            'name' => 'total_space_saved',
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
        $this->values[self::RESULT] = null;
        $this->values[self::ERROR] = null;
        $this->values[self::TOTAL_TIME_MS] = null;
        $this->values[self::TOTAL_SPACE_SAVED] = null;
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

    /**
     * Sets value of 'total_time_ms' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setTotalTimeMs($value)
    {
        return $this->set(self::TOTAL_TIME_MS, $value);
    }

    /**
     * Returns value of 'total_time_ms' property
     *
     * @return integer
     */
    public function getTotalTimeMs()
    {
        $value = $this->get(self::TOTAL_TIME_MS);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'total_space_saved' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setTotalSpaceSaved($value)
    {
        return $this->set(self::TOTAL_SPACE_SAVED, $value);
    }

    /**
     * Returns value of 'total_space_saved' property
     *
     * @return integer
     */
    public function getTotalSpaceSaved()
    {
        $value = $this->get(self::TOTAL_SPACE_SAVED);

        return $value === null ? (int) $value : $value;
    }
}
}
