<?php

/**
 * This file is part of `prooph/event-store-client`.
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
 * TransactionCommit message
 */
class TransactionCommit extends \ProtobufMessage
{
    /* Field index constants */
    const TRANSACTION_ID = 1;
    const REQUIRE_MASTER = 2;

    /* @var array Field descriptors */
    protected static $fields = [
        self::TRANSACTION_ID => [
            'name' => 'transaction_id',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::REQUIRE_MASTER => [
            'name' => 'require_master',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_BOOL,
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
        $this->values[self::REQUIRE_MASTER] = null;
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
     * Sets value of 'require_master' property
     *
     * @param boolean $value Property value
     *
     * @return null
     */
    public function setRequireMaster($value)
    {
        return $this->set(self::REQUIRE_MASTER, $value);
    }

    /**
     * Returns value of 'require_master' property
     *
     * @return boolean
     */
    public function getRequireMaster()
    {
        $value = $this->get(self::REQUIRE_MASTER);

        return $value === null ? (bool) $value : $value;
    }
}
}
