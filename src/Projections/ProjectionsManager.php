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

use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Projections\ProjectionDetails;
use Prooph\EventStore\Projections\ProjectionsManager as ProjectionsManagerInterface;
use Prooph\EventStore\Projections\ProjectionStatistics;
use Prooph\EventStore\Projections\Query;
use Prooph\EventStore\Projections\State;
use Prooph\EventStore\UserCredentials;

class ProjectionsManager implements ProjectionsManagerInterface
{
    private readonly ProjectionsClient $client;

    public function __construct(
        private readonly EndPoint $httpEndPoint,
        int $operationTimeout,
        bool $tlsTerminatedEndpoint = false,
        bool $verifyPeer = true,
        private readonly ?UserCredentials $defaultUserCredentials = null
    ) {
        $this->client = new ProjectionsClient($operationTimeout, $tlsTerminatedEndpoint, $verifyPeer);
    }

    public function enable(string $name, ?UserCredentials $userCredentials = null): void
    {
        if ('' === $name) {
            throw new InvalidArgumentException('Name is required');
        }

        $this->client->enable(
            $this->httpEndPoint,
            $name,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    public function disable(string $name, ?UserCredentials $userCredentials = null): void
    {
        if ('' === $name) {
            throw new InvalidArgumentException('Name is required');
        }

        $this->client->disable(
            $this->httpEndPoint,
            $name,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    public function abort(string $name, ?UserCredentials $userCredentials = null): void
    {
        if ('' === $name) {
            throw new InvalidArgumentException('Name is required');
        }

        $this->client->abort(
            $this->httpEndPoint,
            $name,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    public function createOneTime(
        string $query,
        string $type = 'JS',
        ?UserCredentials $userCredentials = null
    ): void {
        if ('' === $query) {
            throw new InvalidArgumentException('Query is required');
        }

        $this->client->createOneTime(
            $this->httpEndPoint,
            $query,
            $type,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    public function createTransient(
        string $name,
        string $query,
        string $type = 'JS',
        ?UserCredentials $userCredentials = null
    ): void {
        if ('' === $name) {
            throw new InvalidArgumentException('Name is required');
        }

        if ('' === $query) {
            throw new InvalidArgumentException('Query is required');
        }

        $this->client->createTransient(
            $this->httpEndPoint,
            $name,
            $query,
            $type,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    public function createContinuous(
        string $name,
        string $query,
        bool $trackEmittedStreams = false,
        string $type = 'JS',
        ?UserCredentials $userCredentials = null
    ): void {
        if ('' === $name) {
            throw new InvalidArgumentException('Name is required');
        }

        if ('' === $query) {
            throw new InvalidArgumentException('Query is required');
        }

        $this->client->createContinuous(
            $this->httpEndPoint,
            $name,
            $query,
            $trackEmittedStreams,
            $type,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /** @inheritDoc */
    public function listAll(?UserCredentials $userCredentials = null): array
    {
        return $this->client->listAll(
            $this->httpEndPoint,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /** @inheritdoc */
    public function listOneTime(?UserCredentials $userCredentials = null): array
    {
        return $this->client->listOneTime(
            $this->httpEndPoint,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /** @inheritdoc */
    public function listContinuous(?UserCredentials $userCredentials = null): array
    {
        return $this->client->listContinuous(
            $this->httpEndPoint,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    /** @inheritdoc */
    public function getStatus(string $name, ?UserCredentials $userCredentials = null): ProjectionDetails
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

    /** @inheritdoc */
    public function getState(string $name, ?UserCredentials $userCredentials = null): State
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

    /** @inheritdoc */
    public function getPartitionState(
        string $name,
        string $partition,
        ?UserCredentials $userCredentials = null
    ): State {
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

    /** @inheritdoc */
    public function getResult(string $name, ?UserCredentials $userCredentials = null): State
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

    /** @inheritdoc */
    public function getPartitionResult(
        string $name,
        string $partition,
        ?UserCredentials $userCredentials = null
    ): State {
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

    /** @inheritdoc */
    public function getStatistics(string $name, ?UserCredentials $userCredentials = null): ProjectionStatistics
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

    /** @inheritdoc */
    public function getQuery(string $name, ?UserCredentials $userCredentials = null): Query
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

    public function updateQuery(
        string $name,
        string $query,
        ?bool $emitEnabled = null,
        ?UserCredentials $userCredentials = null
    ): void {
        if ('' === $name) {
            throw new InvalidArgumentException('Name is required');
        }

        if ('' === $query) {
            throw new InvalidArgumentException('Query is required');
        }

        $this->client->updateQuery(
            $this->httpEndPoint,
            $name,
            $query,
            $emitEnabled,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    public function delete(
        string $name,
        bool $deleteEmittedStreams = false,
        ?UserCredentials $userCredentials = null
    ): void {
        if ('' === $name) {
            throw new InvalidArgumentException('Name is required');
        }

        $this->client->delete(
            $this->httpEndPoint,
            $name,
            $deleteEmittedStreams,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }

    public function reset(string $name, ?UserCredentials $userCredentials = null): void
    {
        if ('' === $name) {
            throw new InvalidArgumentException('Name is required');
        }

        $this->client->reset(
            $this->httpEndPoint,
            $name,
            $userCredentials ?? $this->defaultUserCredentials
        );
    }
}
