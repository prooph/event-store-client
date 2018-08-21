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

namespace Prooph\EventStoreClient\Internal;

use Amp\Deferred;
use Amp\Promise;
use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStoreClient\EventAppearedOnPersistentSubscription;
use Prooph\EventStoreClient\EventAppearedOnSubscription;
use Prooph\EventStoreClient\Internal\Message\StartPersistentSubscriptionMessage;
use Prooph\EventStoreClient\SubscriptionDroppedOnPersistentSubscription;
use Prooph\EventStoreClient\SubscriptionDroppedOnSubscription;
use Prooph\EventStoreClient\UserCredentials;
use Psr\Log\LoggerInterface as Logger;

class EventStorePersistentSubscription extends AbstractEventStorePersistentSubscription
{
    /** @var EventStoreConnectionLogicHandler */
    private $handler;

    /** @internal  */
    public function __construct(
        string $subscriptionId,
        string $streamId,
        EventAppearedOnPersistentSubscription $eventAppeared,
        ?SubscriptionDroppedOnPersistentSubscription $subscriptionDropped,
        ?UserCredentials $userCredentials,
        Logger $logger,
        bool $verboseLogging,
        ConnectionSettings $settings,
        EventStoreConnectionLogicHandler $handler,
        int $bufferSize = 10,
        bool $autoAck = true
    ) {
        parent::__construct(
            $subscriptionId,
            $streamId,
            $eventAppeared,
            $subscriptionDropped,
            $userCredentials,
            $logger,
            $verboseLogging,
            $settings,
            $bufferSize,
            $autoAck
        );

        $this->handler = $handler;
    }

    public function startSubscription(
        string $subscriptionId,
        string $streamId,
        int $bufferSize,
        ?UserCredentials $userCredentials,
        EventAppearedOnSubscription $onEventAppeared,
        ?SubscriptionDroppedOnSubscription $onSubscriptionDropped,
        ConnectionSettings $settings
    ): Promise {
        $deferred = new Deferred();

        $this->handler->enqueueMessage(new StartPersistentSubscriptionMessage(
            $deferred,
            $subscriptionId,
            $streamId,
            $bufferSize,
            $userCredentials,
            $onEventAppeared,
            $onSubscriptionDropped,
            $settings->maxRetries(),
            $settings->operationTimeout()
        ));

        return $deferred->promise();
    }
}
