<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2022 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2022 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\PersistentSubscriptions;

use Amp\Promise;
use Prooph\EventStore\Async\PersistentSubscriptions\PersistentSubscriptionsManager as AsyncPersistentSubscriptionsManager;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\PersistentSubscriptions\PersistentSubscriptionDetails;
use Prooph\EventStore\UserCredentials;

class PersistentSubscriptionsManager implements AsyncPersistentSubscriptionsManager
{
    private PersistentSubscriptionsClient $client;
    private EndPoint $httpEndPoint;
    private ?UserCredentials $defaultUserCredentials;

    public function __construct(
        EndPoint $httpEndPoint,
        int $operationTimeout,
        bool $tlsTerminatedEndpoint = false,
        bool $verifyPeer = true,
        ?UserCredentials $defaultUserCredentials = null
    ) {
        $this->client = new PersistentSubscriptionsClient($operationTimeout, $tlsTerminatedEndpoint, $verifyPeer);
        $this->httpEndPoint = $httpEndPoint;
        $this->defaultUserCredentials = $defaultUserCredentials;
    }

    /**
     * @return Promise<PersistentSubscriptionDetails>
     */
    public function describe(
        string $stream,
        string $subscriptionName,
        ?UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if (empty($subscriptionName)) {
            throw new InvalidArgumentException('Subscription name cannot be empty');
        }

        return $this->client->describe(
            $this->httpEndPoint,
            $stream,
            $subscriptionName,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /** @return Promise<void> */
    public function replayParkedMessages(
        string $stream,
        string $subscriptionName,
        ?UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if (empty($subscriptionName)) {
            throw new InvalidArgumentException('Subscription name cannot be empty');
        }

        return $this->client->replayParkedMessages(
            $this->httpEndPoint,
            $stream,
            $subscriptionName,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /**
     * @return Promise<list<PersistentSubscriptionDetails>>
     */
    public function list(?string $stream = null, ?UserCredentials $userCredentials = null): Promise
    {
        if ('' === $stream) {
            $stream = null;
        }

        return $this->client->list(
            $this->httpEndPoint,
            $stream,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }
}
