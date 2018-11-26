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

namespace Prooph\EventStoreClient\PersistentSubscriptions;

use Amp\Promise;
use Prooph\EventStoreClient\EndPoint;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Transport\Http\EndpointExtensions;
use Prooph\EventStoreClient\UserCredentials;

class PersistentSubscriptionsManager
{
    /** @var PersistentSubscriptionsClient */
    private $client;
    /** @var EndPoint */
    private $httpEndPoint;
    /** @var string */
    private $httpSchema;

    public function __construct(
        EndPoint $httpEndPoint,
        int $operationTimeout,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ) {
        $this->client = new PersistentSubscriptionsClient($operationTimeout);
        $this->httpEndPoint = $httpEndPoint;
        $this->httpSchema = $httpSchema;
    }

    /**
     * @param string $stream
     * @param string $subscriptionName
     * @param null|UserCredentials $userCredentials
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
            $userCredentials,
            $this->httpSchema
        );
    }

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
            $userCredentials,
            $this->httpSchema
        );
    }

    /**
     * @param null|string $stream
     * @param null|UserCredentials $userCredentials
     * @return Promise<PersistentSubscriptionDetails[]>
     */
    public function list(?string $stream = null, ?UserCredentials $userCredentials = null): Promise
    {
        if ('' === $stream) {
            $stream = null;
        }

        return $this->client->list(
            $this->httpEndPoint,
            $stream,
            $userCredentials,
            $this->httpSchema
        );
    }
}
