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

use Amp\Promise;
use Prooph\EventStore\Async\Projections\ProjectionsManager as AsyncProjectionsManager;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\UserCredentials;

class ProjectionsManager implements AsyncProjectionsManager
{
    private ProjectionsClient $client;
    private EndPoint $httpEndPoint;
    private ?UserCredentials $defaultUserCredentials;

    public function __construct(
        EndPoint $httpEndPoint,
        int $operationTimeout,
        bool $tlsTerminatedEndpoint = false,
        bool $verifyPeer = true,
        ?UserCredentials $defaultUserCredentials = null
    ) {
        $this->client = new ProjectionsClient($operationTimeout, $tlsTerminatedEndpoint, $verifyPeer);
        $this->httpEndPoint = $httpEndPoint;
        $this->defaultUserCredentials = $defaultUserCredentials;
    }

    /** {@inheritdoc} */
    public function enableAsync(string $name, ?UserCredentials $userCredentials = null): Promise
    {
        if ('' === $name) {
            throw new InvalidArgumentException('Name is required');
        }

        return $this->client->enable(
            $this->httpEndPoint,
            $name,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /** {@inheritdoc} */
    public function disableAsync(string $name, ?UserCredentials $userCredentials = null): Promise
    {
        if ('' === $name) {
            throw new InvalidArgumentException('Name is required');
        }

        return $this->client->disable(
            $this->httpEndPoint,
            $name,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /** {@inheritdoc} */
    public function abortAsync(string $name, ?UserCredentials $userCredentials = null): Promise
    {
        if ('' === $name) {
            throw new InvalidArgumentException('Name is required');
        }

        return $this->client->abort(
            $this->httpEndPoint,
            $name,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /** {@inheritdoc} */
    public function createOneTimeAsync(
        string $query,
        string $type = 'JS',
        ?UserCredentials $userCredentials = null
    ): Promise {
        if ('' === $query) {
            throw new InvalidArgumentException('Query is required');
        }

        return $this->client->createOneTime(
            $this->httpEndPoint,
            $query,
            $type,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /** {@inheritdoc} */
    public function createTransientAsync(
        string $name,
        string $query,
        string $type = 'JS',
        ?UserCredentials $userCredentials = null
    ): Promise {
        if ('' === $name) {
            throw new InvalidArgumentException('Name is required');
        }

        if ('' === $query) {
            throw new InvalidArgumentException('Query is required');
        }

        return $this->client->createTransient(
            $this->httpEndPoint,
            $name,
            $query,
            $type,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /** {@inheritdoc} */
    public function createContinuousAsync(
        string $name,
        string $query,
        bool $trackEmittedStreams = false,
        string $type = 'JS',
        ?UserCredentials $userCredentials = null
    ): Promise {
        if ('' === $name) {
            throw new InvalidArgumentException('Name is required');
        }

        if ('' === $query) {
            throw new InvalidArgumentException('Query is required');
        }

        return $this->client->createContinuous(
            $this->httpEndPoint,
            $name,
            $query,
            $trackEmittedStreams,
            $type,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /** {@inheritdoc} */
    public function listAllAsync(?UserCredentials $userCredentials = null): Promise
    {
        return $this->client->listAll(
            $this->httpEndPoint,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /** {@inheritdoc} */
    public function listOneTimeAsync(?UserCredentials $userCredentials = null): Promise
    {
        return $this->client->listOneTime(
            $this->httpEndPoint,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /** {@inheritdoc} */
    public function listContinuousAsync(?UserCredentials $userCredentials = null): Promise
    {
        return $this->client->listContinuous(
            $this->httpEndPoint,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /** {@inheritdoc} */
    public function getStatusAsync(string $name, ?UserCredentials $userCredentials = null): Promise
    {
        if ('' === $name) {
            throw new InvalidArgumentException('Name is required');
        }

        return $this->client->getStatus(
            $this->httpEndPoint,
            $name,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /** {@inheritdoc} */
    public function getStateAsync(string $name, ?UserCredentials $userCredentials = null): Promise
    {
        if ('' === $name) {
            throw new InvalidArgumentException('Name is required');
        }

        return $this->client->getState(
            $this->httpEndPoint,
            $name,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /** {@inheritdoc} */
    public function getPartitionStateAsync(
        string $name,
        string $partition,
        ?UserCredentials $userCredentials = null
    ): Promise {
        if ('' === $name) {
            throw new InvalidArgumentException('Name is required');
        }

        if ('' === $partition) {
            throw new InvalidArgumentException('Partition is required');
        }

        return $this->client->getPartitionState(
            $this->httpEndPoint,
            $name,
            $partition,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /** {@inheritdoc} */
    public function getResultAsync(string $name, ?UserCredentials $userCredentials = null): Promise
    {
        if ('' === $name) {
            throw new InvalidArgumentException('Name is required');
        }

        return $this->client->getResult(
            $this->httpEndPoint,
            $name,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /** {@inheritdoc} */
    public function getPartitionResultAsync(
        string $name,
        string $partition,
        ?UserCredentials $userCredentials = null
    ): Promise {
        if ('' === $name) {
            throw new InvalidArgumentException('Name is required');
        }

        if ('' === $partition) {
            throw new InvalidArgumentException('Partition is required');
        }

        return $this->client->getPartitionResult(
            $this->httpEndPoint,
            $name,
            $partition,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /** {@inheritdoc} */
    public function getStatisticsAsync(string $name, ?UserCredentials $userCredentials = null): Promise
    {
        if ('' === $name) {
            throw new InvalidArgumentException('Name is required');
        }

        return $this->client->getStatistics(
            $this->httpEndPoint,
            $name,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /** {@inheritdoc} */
    public function getQueryAsync(string $name, ?UserCredentials $userCredentials = null): Promise
    {
        if ('' === $name) {
            throw new InvalidArgumentException('Name is required');
        }

        return $this->client->getQuery(
            $this->httpEndPoint,
            $name,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /** {@inheritdoc} */
    public function updateQueryAsync(
        string $name,
        string $query,
        ?bool $emitEnabled = null,
        ?UserCredentials $userCredentials = null
    ): Promise {
        if ('' === $name) {
            throw new InvalidArgumentException('Name is required');
        }

        if ('' === $query) {
            throw new InvalidArgumentException('Query is required');
        }

        return $this->client->updateQuery(
            $this->httpEndPoint,
            $name,
            $query,
            $emitEnabled,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /** {@inheritdoc} */
    public function deleteAsync(
        string $name,
        bool $deleteEmittedStreams = false,
        ?UserCredentials $userCredentials = null
    ): Promise {
        if ('' === $name) {
            throw new InvalidArgumentException('Name is required');
        }

        return $this->client->delete(
            $this->httpEndPoint,
            $name,
            $deleteEmittedStreams,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /** {@inheritdoc} */
    public function resetAsync(string $name, ?UserCredentials $userCredentials = null): Promise
    {
        if ('' === $name) {
            throw new InvalidArgumentException('Name is required');
        }

        return $this->client->reset(
            $this->httpEndPoint,
            $name,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }
}
