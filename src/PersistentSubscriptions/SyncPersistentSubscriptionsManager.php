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
use Prooph\EventStoreClient\Transport\Http\EndpointExtensions;
use Prooph\EventStoreClient\UserCredentials;
use Throwable;

class SyncPersistentSubscriptionsManager
{
    /** @var AsyncPersistentSubscriptionsManager */
    private $manager;

    public function __construct(
        EndPoint $httpEndPoint,
        int $operationTimeout,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ) {
        $this->manager = new AsyncPersistentSubscriptionsManager(
            $httpEndPoint,
            $operationTimeout,
            $httpSchema
        );
    }

    /**
     * @param string $stream
     * @param string $subscriptionName
     * @param null|UserCredentials $userCredentials
     *
     * @return PersistentSubscriptionDetails
     *
     * @throws Throwable
     */
    public function describe(
        string $stream,
        string $subscriptionName,
        ?UserCredentials $userCredentials = null
    ): PersistentSubscriptionDetails {
        return Promise\wait($this->manager->describe(
            $stream,
            $subscriptionName,
            $userCredentials
        ));
    }

    /**
     * @param string $stream
     * @param string $subscriptionName
     * @param null|UserCredentials $userCredentials
     *
     * @throws Throwable
     */
    public function replayParkedMessages(
        string $stream,
        string $subscriptionName,
        ?UserCredentials $userCredentials = null
    ): void {
        Promise\wait($this->manager->replayParkedMessages(
            $stream,
            $subscriptionName,
            $userCredentials
        ));
    }

    /**
     * @param null|string $stream
     * @param null|UserCredentials $userCredentials
     *
     * @return PersistentSubscriptionDetails[]
     *
     * @throws Throwable
     */
    public function list(?string $stream = null, ?UserCredentials $userCredentials = null): array
    {
        return Promise\wait($this->manager->list(
            $stream,
            $userCredentials
        ));
    }
}
