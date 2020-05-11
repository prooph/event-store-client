<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2020 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2020 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\Projections;

use function Amp\call;
use Amp\Delayed;
use Amp\Promise;
use Generator;
use Prooph\EventStore\Async\Projections\QueryManager as AsyncQueryManager;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Projections\ProjectionDetails;
use Prooph\EventStore\Transport\Http\EndpointExtensions;
use Prooph\EventStore\UserCredentials;

/**
 * API for executing queries in the Event Store through PHP code.
 * Communicates with the Event Store over the RESTful API.
 */
class QueryManager implements AsyncQueryManager
{
    private int $queryTimeout;
    private ProjectionsManager $projectionsManager;
    private ?UserCredentials $defaultUserCredentials;

    public function __construct(
        EndPoint $httpEndPoint,
        int $projectionOperationTimeout,
        int $queryTimeout,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA,
        ?UserCredentials $defaultUserCredentials = null
    ) {
        $this->queryTimeout = $queryTimeout;
        $this->projectionsManager = new ProjectionsManager(
            $httpEndPoint,
            $projectionOperationTimeout,
            $httpSchema
        );
        $this->defaultUserCredentials = $defaultUserCredentials;
    }

    /** {@inheritdoc} */
    public function executeAsync(
        string $name,
        string $query,
        int $initialPollingDelay,
        int $maximumPollingDelay,
        string $type = 'JS',
        ?UserCredentials $userCredentials = null
    ): Promise {
        $userCredentials ??= $this->defaultUserCredentials;

        $promise = call(function () use ($name, $query, $initialPollingDelay,
            $maximumPollingDelay, $type, $userCredentials
        ): Generator {
            yield $this->projectionsManager->createTransientAsync(
                    $name,
                    $query,
                    $type,
                    $userCredentials
                );

            yield $this->waitForCompletedAsync(
                    $name,
                    $initialPollingDelay,
                    $maximumPollingDelay,
                    $userCredentials
                );

            return yield $this->projectionsManager->getStateAsync(
                    $name,
                    $userCredentials
                );
        });

        return Promise\timeout($promise, $this->queryTimeout);
    }

    /** {@inheritdoc} */
    private function waitForCompletedAsync(
        string $name,
        int $initialPollingDelay,
        int $maximumPollingDelay,
        ?UserCredentials $userCredentials
    ): Promise {
        return call(function () use ($name, $initialPollingDelay, $maximumPollingDelay, $userCredentials): Generator {
            $attempts = 0;
            $status = yield $this->getStatusAsync($name, $userCredentials);

            while (false === \strpos($status->status(), 'Completed')) {
                $attempts++;

                yield $this->delayPollingAsync(
                    $attempts,
                    $initialPollingDelay,
                    $maximumPollingDelay
                );

                $status = yield $this->getStatusAsync($name, $userCredentials);
            }
        });
    }

    /** {@inheritdoc} */
    private function delayPollingAsync(
        int $attempts,
        int $initialPollingDelay,
        int $maximumPollingDelay
    ): Promise {
        return call(function () use ($attempts, $initialPollingDelay, $maximumPollingDelay): Generator {
            $delayInMilliseconds = $initialPollingDelay * (2 ** $attempts - 1);
            $delayInMilliseconds = (int) \min($delayInMilliseconds, $maximumPollingDelay);

            yield new Delayed($delayInMilliseconds);
        });
    }

    /** @return Promise<ProjectionDetails> */
    private function getStatusAsync(string $name, ?UserCredentials $userCredentials): Promise
    {
        return $this->projectionsManager->getStatusAsync($name, $userCredentials);
    }
}
