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
 * ReadAllEvents message
 */
class ReadAllEvents extends \ProtobufMessage
{
    /* Field index constants */
    const COMMIT_POSITION = 1;
    const PREPARE_POSITION = 2;
    const MAX_COUNT = 3;
    const RESOLVE_LINK_TOS = 4;
    const REQUIRE_MASTER = 5;

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
        self::MAX_COUNT => [
            'name' => 'max_count',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::RESOLVE_LINK_TOS => [
            'name' => 'resolve_link_tos',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_BOOL,
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
        $this->values[self::COMMIT_POSITION] = null;
        $this->values[self::PREPARE_POSITION] = null;
        $this->values[self::MAX_COUNT] = null;
        $this->values[self::RESOLVE_LINK_TOS] = null;
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
     * Sets value of 'max_count' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setMaxCount($value)
    {
        return $this->set(self::MAX_COUNT, $value);
    }

    /**
     * Returns value of 'max_count' property
     *
     * @return integer
     */
    public function getMaxCount()
    {
        $value = $this->get(self::MAX_COUNT);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'resolve_link_tos' property
     *
     * @param boolean $value Property value
     *
     * @return null
     */
    public function setResolveLinkTos($value)
    {
        return $this->set(self::RESOLVE_LINK_TOS, $value);
    }

    /**
     * Returns value of 'resolve_link_tos' property
     *
     * @return boolean
     */
    public function getResolveLinkTos()
    {
        $value = $this->get(self::RESOLVE_LINK_TOS);

        return $value === null ? (bool) $value : $value;
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
