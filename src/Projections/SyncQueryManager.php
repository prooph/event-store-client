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

namespace Prooph\EventStoreClient\Projections;

use Amp\Delayed;
use Amp\Promise;
use Generator;
use Prooph\EventStoreClient\EndPoint;
use Prooph\EventStoreClient\UserCredentials;
use function Amp\call;
use Throwable;

/**
 * API for executing queries in the Event Store through PHP code.
 * Communicates with the Event Store over the RESTful API.
 */
class SyncQueryManager
{
    /** @var AsyncQueryManager */
    private $manager;

    public function __construct(
        EndPoint $httpEndPoint,
        int $projectionOperationTimeout,
        int $queryTimeout
    ) {
        $this->manager = new AsyncQueryManager(
            $httpEndPoint,
            $projectionOperationTimeout,
            $queryTimeout
        );
    }

    /**
     * Synchronously executes a query
     *
     * Creates a new transient projection and polls its status until it is Completed
     *
     * returns String of JSON containing query result
     *
     * @param string $name A name for the query
     * @param string $query The source code for the query
     * @param int $initialPollingDelay Initial time to wait between polling for projection status
     * @param int $maximumPollingDelay Maximum time to wait between polling for projection status
     * @param UserCredentials|null $userCredentials Credentials for a user with permission to create a query
     *
     * @return string
     *
     * @throws Throwable
     */
    public function execute(
        string $name,
        string $query,
        int $initialPollingDelay,
        int $maximumPollingDelay,
        ?UserCredentials $userCredentials = null
    ): string {
        return Promise\wait($this->manager->executeAsync(
            $name,
            $query,
            $initialPollingDelay,
            $maximumPollingDelay,
            $userCredentials
        ));
    }
}
