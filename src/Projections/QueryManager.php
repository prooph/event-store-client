<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2024 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2024 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\Projections;

use function Amp\async;
use function Amp\delay;

use Amp\TimeoutCancellation;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Projections\ProjectionDetails;
use Prooph\EventStore\Projections\QueryManager as QueryManagerInterface;
use Prooph\EventStore\Projections\State;
use Prooph\EventStore\UserCredentials;

/**
 * API for executing queries in the Event Store through PHP code.
 * Communicates with the Event Store over the RESTful API.
 */
class QueryManager implements QueryManagerInterface
{
    private readonly ProjectionsManager $projectionsManager;

    public function __construct(
        EndPoint $httpEndPoint,
        int $projectionOperationTimeout,
        private readonly int $queryTimeout,
        bool $tlsTerminatedEndpoint = false,
        bool $verifyPeer = true,
        private readonly ?UserCredentials $defaultUserCredentials = null
    ) {
        $this->projectionsManager = new ProjectionsManager(
            $httpEndPoint,
            $projectionOperationTimeout,
            $tlsTerminatedEndpoint,
            $verifyPeer,
        );
    }

    public function execute(
        string $name,
        string $query,
        int $initialPollingDelay,
        int $maximumPollingDelay,
        string $type = 'JS',
        ?UserCredentials $userCredentials = null
    ): State {
        return async(function () use ($name, $query, $type, $initialPollingDelay, $maximumPollingDelay, $userCredentials): State {
            $this->projectionsManager->createTransient(
                $name,
                $query,
                $type,
                $userCredentials ?? $this->defaultUserCredentials
            );

            $this->waitForCompleted(
                $name,
                $initialPollingDelay,
                $maximumPollingDelay,
                $userCredentials ?? $this->defaultUserCredentials
            );

            return $this->projectionsManager->getState(
                $name,
                $userCredentials ?? $this->defaultUserCredentials
            );
        })->await(new TimeoutCancellation($this->queryTimeout));
    }

    private function waitForCompleted(
        string $name,
        int $initialPollingDelay,
        int $maximumPollingDelay,
        ?UserCredentials $userCredentials
    ): void {
        $attempts = 0;
        $status = $this->getStatus($name, $userCredentials);

        while (! \str_contains($status->status(), 'Completed')) {
            $attempts++;

            delay((int) \min(
                $initialPollingDelay * (2 ** $attempts - 1),
                $maximumPollingDelay
            ));

            $status = $this->getStatus($name, $userCredentials);
        }
    }

    private function getStatus(string $name, ?UserCredentials $userCredentials): ProjectionDetails
    {
        return $this->projectionsManager->getStatus($name, $userCredentials);
    }
}
