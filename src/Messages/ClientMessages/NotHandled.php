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
 * NotHandled message
 */
class NotHandled extends \ProtobufMessage
{
    /* Field index constants */
    const REASON = 1;
    const ADDITIONAL_INFO = 2;

    /* @var array Field descriptors */
    protected static $fields = [
        self::REASON => [
            'name' => 'reason',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::ADDITIONAL_INFO => [
            'name' => 'additional_info',
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
        $this->values[self::REASON] = null;
        $this->values[self::ADDITIONAL_INFO] = null;
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
     * Sets value of 'reason' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setReason($value)
    {
        return $this->set(self::REASON, $value);
    }

    /**
     * Returns value of 'reason' property
     *
     * @return integer
     */
    public function getReason()
    {
        $value = $this->get(self::REASON);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'additional_info' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setAdditionalInfo($value)
    {
        return $this->set(self::ADDITIONAL_INFO, $value);
    }

    /**
     * Returns value of 'additional_info' property
     *
     * @return string
     */
    public function getAdditionalInfo()
    {
        $value = $this->get(self::ADDITIONAL_INFO);

        return $value === null ? (string) $value : $value;
    }
}
}
