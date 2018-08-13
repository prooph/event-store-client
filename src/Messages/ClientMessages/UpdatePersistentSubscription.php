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
 * UpdatePersistentSubscription message
 */
class UpdatePersistentSubscription extends \ProtobufMessage
{
    /* Field index constants */
    const SUBSCRIPTION_GROUP_NAME = 1;
    const EVENT_STREAM_ID = 2;
    const RESOLVE_LINK_TOS = 3;
    const START_FROM = 4;
    const MESSAGE_TIMEOUT_MILLISECONDS = 5;
    const RECORD_STATISTICS = 6;
    const LIVE_BUFFER_SIZE = 7;
    const READ_BATCH_SIZE = 8;
    const BUFFER_SIZE = 9;
    const MAX_RETRY_COUNT = 10;
    const PREFER_ROUND_ROBIN = 11;
    const CHECKPOINT_AFTER_TIME = 12;
    const CHECKPOINT_MAX_COUNT = 13;
    const CHECKPOINT_MIN_COUNT = 14;
    const SUBSCRIBER_MAX_COUNT = 15;
    const NAMED_CONSUMER_STRATEGY = 16;

    /* @var array Field descriptors */
    protected static $fields = [
        self::SUBSCRIPTION_GROUP_NAME => [
            'name' => 'subscription_group_name',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_STRING,
        ],
        self::EVENT_STREAM_ID => [
            'name' => 'event_stream_id',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_STRING,
        ],
        self::RESOLVE_LINK_TOS => [
            'name' => 'resolve_link_tos',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_BOOL,
        ],
        self::START_FROM => [
            'name' => 'start_from',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::MESSAGE_TIMEOUT_MILLISECONDS => [
            'name' => 'message_timeout_milliseconds',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::RECORD_STATISTICS => [
            'name' => 'record_statistics',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_BOOL,
        ],
        self::LIVE_BUFFER_SIZE => [
            'name' => 'live_buffer_size',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::READ_BATCH_SIZE => [
            'name' => 'read_batch_size',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::BUFFER_SIZE => [
            'name' => 'buffer_size',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::MAX_RETRY_COUNT => [
            'name' => 'max_retry_count',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::PREFER_ROUND_ROBIN => [
            'name' => 'prefer_round_robin',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_BOOL,
        ],
        self::CHECKPOINT_AFTER_TIME => [
            'name' => 'checkpoint_after_time',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::CHECKPOINT_MAX_COUNT => [
            'name' => 'checkpoint_max_count',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::CHECKPOINT_MIN_COUNT => [
            'name' => 'checkpoint_min_count',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::SUBSCRIBER_MAX_COUNT => [
            'name' => 'subscriber_max_count',
            'required' => true,
            'type' => \ProtobufMessage::PB_TYPE_INT,
        ],
        self::NAMED_CONSUMER_STRATEGY => [
            'name' => 'named_consumer_strategy',
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
        $this->values[self::SUBSCRIPTION_GROUP_NAME] = null;
        $this->values[self::EVENT_STREAM_ID] = null;
        $this->values[self::RESOLVE_LINK_TOS] = null;
        $this->values[self::START_FROM] = null;
        $this->values[self::MESSAGE_TIMEOUT_MILLISECONDS] = null;
        $this->values[self::RECORD_STATISTICS] = null;
        $this->values[self::LIVE_BUFFER_SIZE] = null;
        $this->values[self::READ_BATCH_SIZE] = null;
        $this->values[self::BUFFER_SIZE] = null;
        $this->values[self::MAX_RETRY_COUNT] = null;
        $this->values[self::PREFER_ROUND_ROBIN] = null;
        $this->values[self::CHECKPOINT_AFTER_TIME] = null;
        $this->values[self::CHECKPOINT_MAX_COUNT] = null;
        $this->values[self::CHECKPOINT_MIN_COUNT] = null;
        $this->values[self::SUBSCRIBER_MAX_COUNT] = null;
        $this->values[self::NAMED_CONSUMER_STRATEGY] = null;
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
     * Sets value of 'subscription_group_name' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setSubscriptionGroupName($value)
    {
        return $this->set(self::SUBSCRIPTION_GROUP_NAME, $value);
    }

    /**
     * Returns value of 'subscription_group_name' property
     *
     * @return string
     */
    public function getSubscriptionGroupName()
    {
        $value = $this->get(self::SUBSCRIPTION_GROUP_NAME);

        return $value === null ? (string) $value : $value;
    }

    /**
     * Sets value of 'event_stream_id' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setEventStreamId($value)
    {
        return $this->set(self::EVENT_STREAM_ID, $value);
    }

    /**
     * Returns value of 'event_stream_id' property
     *
     * @return string
     */
    public function getEventStreamId()
    {
        $value = $this->get(self::EVENT_STREAM_ID);

        return $value === null ? (string) $value : $value;
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
     * Sets value of 'start_from' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setStartFrom($value)
    {
        return $this->set(self::START_FROM, $value);
    }

    /**
     * Returns value of 'start_from' property
     *
     * @return integer
     */
    public function getStartFrom()
    {
        $value = $this->get(self::START_FROM);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'message_timeout_milliseconds' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setMessageTimeoutMilliseconds($value)
    {
        return $this->set(self::MESSAGE_TIMEOUT_MILLISECONDS, $value);
    }

    /**
     * Returns value of 'message_timeout_milliseconds' property
     *
     * @return integer
     */
    public function getMessageTimeoutMilliseconds()
    {
        $value = $this->get(self::MESSAGE_TIMEOUT_MILLISECONDS);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'record_statistics' property
     *
     * @param boolean $value Property value
     *
     * @return null
     */
    public function setRecordStatistics($value)
    {
        return $this->set(self::RECORD_STATISTICS, $value);
    }

    /**
     * Returns value of 'record_statistics' property
     *
     * @return boolean
     */
    public function getRecordStatistics()
    {
        $value = $this->get(self::RECORD_STATISTICS);

        return $value === null ? (bool) $value : $value;
    }

    /**
     * Sets value of 'live_buffer_size' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setLiveBufferSize($value)
    {
        return $this->set(self::LIVE_BUFFER_SIZE, $value);
    }

    /**
     * Returns value of 'live_buffer_size' property
     *
     * @return integer
     */
    public function getLiveBufferSize()
    {
        $value = $this->get(self::LIVE_BUFFER_SIZE);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'read_batch_size' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setReadBatchSize($value)
    {
        return $this->set(self::READ_BATCH_SIZE, $value);
    }

    /**
     * Returns value of 'read_batch_size' property
     *
     * @return integer
     */
    public function getReadBatchSize()
    {
        $value = $this->get(self::READ_BATCH_SIZE);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'buffer_size' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setBufferSize($value)
    {
        return $this->set(self::BUFFER_SIZE, $value);
    }

    /**
     * Returns value of 'buffer_size' property
     *
     * @return integer
     */
    public function getBufferSize()
    {
        $value = $this->get(self::BUFFER_SIZE);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'max_retry_count' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setMaxRetryCount($value)
    {
        return $this->set(self::MAX_RETRY_COUNT, $value);
    }

    /**
     * Returns value of 'max_retry_count' property
     *
     * @return integer
     */
    public function getMaxRetryCount()
    {
        $value = $this->get(self::MAX_RETRY_COUNT);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'prefer_round_robin' property
     *
     * @param boolean $value Property value
     *
     * @return null
     */
    public function setPreferRoundRobin($value)
    {
        return $this->set(self::PREFER_ROUND_ROBIN, $value);
    }

    /**
     * Returns value of 'prefer_round_robin' property
     *
     * @return boolean
     */
    public function getPreferRoundRobin()
    {
        $value = $this->get(self::PREFER_ROUND_ROBIN);

        return $value === null ? (bool) $value : $value;
    }

    /**
     * Sets value of 'checkpoint_after_time' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setCheckpointAfterTime($value)
    {
        return $this->set(self::CHECKPOINT_AFTER_TIME, $value);
    }

    /**
     * Returns value of 'checkpoint_after_time' property
     *
     * @return integer
     */
    public function getCheckpointAfterTime()
    {
        $value = $this->get(self::CHECKPOINT_AFTER_TIME);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'checkpoint_max_count' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setCheckpointMaxCount($value)
    {
        return $this->set(self::CHECKPOINT_MAX_COUNT, $value);
    }

    /**
     * Returns value of 'checkpoint_max_count' property
     *
     * @return integer
     */
    public function getCheckpointMaxCount()
    {
        $value = $this->get(self::CHECKPOINT_MAX_COUNT);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'checkpoint_min_count' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setCheckpointMinCount($value)
    {
        return $this->set(self::CHECKPOINT_MIN_COUNT, $value);
    }

    /**
     * Returns value of 'checkpoint_min_count' property
     *
     * @return integer
     */
    public function getCheckpointMinCount()
    {
        $value = $this->get(self::CHECKPOINT_MIN_COUNT);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'subscriber_max_count' property
     *
     * @param integer $value Property value
     *
     * @return null
     */
    public function setSubscriberMaxCount($value)
    {
        return $this->set(self::SUBSCRIBER_MAX_COUNT, $value);
    }

    /**
     * Returns value of 'subscriber_max_count' property
     *
     * @return integer
     */
    public function getSubscriberMaxCount()
    {
        $value = $this->get(self::SUBSCRIBER_MAX_COUNT);

        return $value === null ? (int) $value : $value;
    }

    /**
     * Sets value of 'named_consumer_strategy' property
     *
     * @param string $value Property value
     *
     * @return null
     */
    public function setNamedConsumerStrategy($value)
    {
        return $this->set(self::NAMED_CONSUMER_STRATEGY, $value);
    }

    /**
     * Returns value of 'named_consumer_strategy' property
     *
     * @return string
     */
    public function getNamedConsumerStrategy()
    {
        $value = $this->get(self::NAMED_CONSUMER_STRATEGY);

        return $value === null ? (string) $value : $value;
    }
}
}
