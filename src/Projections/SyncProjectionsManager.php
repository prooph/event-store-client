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

use Amp\Promise;
use Prooph\EventStoreClient\EndPoint;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\PersistentSubscriptions\AsyncPersistentSubscriptionsManager;
use Prooph\EventStoreClient\Transport\Http\EndpointExtensions;
use Prooph\EventStoreClient\UserCredentials;
use Throwable;

class SyncProjectionsManager
{
    /** @var AsyncProjectionsManager */
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
     * Synchronously enables a projection
     *
     * @throws Throwable
     */
    public function enable(string $name, ?UserCredentials $userCredentials = null): void
    {
        Promise\wait($this->manager->enableAsync($name, $userCredentials));
    }

    /**
     * Synchronously aborts and disables a projection without writing a checkpoint
     *
     * @throws Throwable
     */
    public function disable(string $name, ?UserCredentials $userCredentials = null): void
    {
        Promise\wait($this->manager->disableAsync($name, $userCredentials));
    }

    /**
     * Synchronously disables a projection
     *
     * @throws Throwable
     */
    public function abort(string $name, ?UserCredentials $userCredentials = null): void
    {
        Promise\wait($this->manager->abortAsync($name, $userCredentials));
    }

    /**
     * Synchronously creates a one-time query
     *
     * @throws Throwable
     */
    public function createOneTime(
        string $query,
        ?UserCredentials $userCredentials = null
    ): void {
        Promise\wait($this->manager->createOneTimeAsync($query, $userCredentials));
    }

    /**
     * Synchronously creates a one-time query
     *
     * @throws Throwable
     */
    public function createTransient(
        string $name,
        string $query,
        ?UserCredentials $userCredentials = null
    ): void {
        Promise\wait($this->manager->createTransientAsync(
            $name,
            $query,
            $userCredentials
        ));
    }

    /**
     * Synchronously creates a continuous projection
     *
     * @throws Throwable
     */
    public function createContinuous(
        string $name,
        string $query,
        bool $trackEmittedStreams = false,
        ?UserCredentials $userCredentials = null
    ): void {
        Promise\wait($this->manager->createContinuousAsync(
            $name,
            $query,
            $trackEmittedStreams,
            $userCredentials
        ));
    }

    /**
     * Synchronously lists all projections
     *
     * @return ProjectionDetails[]
     *
     * @throws Throwable
     */
    public function listAll(?UserCredentials $userCredentials = null): array
    {
        return Promise\wait($this->manager->listAllAsync($userCredentials));
    }

    /**
     * Synchronously lists all one-time projections
     *
     * @return ProjectionDetails[]
     *
     * @throws Throwable
     */
    public function listOneTime(?UserCredentials $userCredentials = null): array
    {
        return Promise\wait($this->manager->listOneTimeAsync($userCredentials));
    }

    /**
     * Synchronously lists this status of all continuous projections
     *
     * @return ProjectionDetails[]
     *
     * @throws Throwable
     */
    public function listContinuous(?UserCredentials $userCredentials = null): array
    {
        return Promise\wait($this->manager->listContinuousAsync($userCredentials));
    }

    /**
     * Synchronously gets the status of a projection
     *
     * returns String of JSON containing projection status
     *
     * @throws Throwable
     */
    public function getStatus(string $name, ?UserCredentials $userCredentials = null): string
    {
        return Promise\wait($this->manager->getStatusAsync(
            $name,
            $userCredentials
        ));
    }

    /**
     * Synchronously gets the state of a projection.
     *
     * returns String of JSON containing projection state
     *
     * @throws Throwable
     */
    public function getState(string $name, ?UserCredentials $userCredentials = null): string
    {
        return Promise\wait($this->manager->getStateAsync(
            $name,
            $userCredentials
        ));
    }

    /**
     * Synchronously gets the state of a projection for a specified partition
     *
     * returns String of JSON containing projection state
     *
     * @throws Throwable
     */
    public function getPartitionState(
        string $name,
        string $partition,
        ?UserCredentials $userCredentials = null
    ): string {
        return Promise\wait($this->manager->getPartitionStateAsync(
            $name,
            $partition,
            $userCredentials
        ));
    }

    /**
     * Synchronously gets the resut of a projection
     *
     * returns String of JSON containing projection result
     *
     * @throws Throwable
     */
    public function getResult(string $name, ?UserCredentials $userCredentials = null): string
    {
        return Promise\wait($this->manager->getResultAsync(
            $name,
            $userCredentials
        ));
    }

    /**
     * Synchronously gets the result of a projection for a specified partition
     *
     * returns String of JSON containing projection result
     *
     * @throws Throwable
     */
    public function getPartitionResult(
        string $name,
        string $partition,
        ?UserCredentials $userCredentials = null
    ): string {
        return Promise\wait($this->manager->getPartitionResultAsync(
            $name,
            $partition,
            $userCredentials
        ));
    }

    /**
     * Synchronously gets the statistics of a projection
     *
     * returns String of JSON containing projection statistics
     *
     * @throws Throwable
     */
    public function getStatistics(string $name, ?UserCredentials $userCredentials = null): string
    {
        return Promise\wait($this->manager->getStatisticsAsync(
            $name,
            $userCredentials
        ));
    }

    /**
     * Synchronously gets the status of a query
     *
     * @throws Throwable
     */
    public function getQuery(string $name, ?UserCredentials $userCredentials = null): string
    {
        return Promise\wait($this->manager->getQueryAsync(
            $name,
            $userCredentials
        ));
    }

    /**
     * Synchronously updates the definition of a query
     *
     * @throws Throwable
     */
    public function updateQuery(
        string $name,
        string $query,
        bool $emitEnabled = false,
        ?UserCredentials $userCredentials = null
    ): void {
        Promise\wait($this->manager->updateQueryAsync(
            $name,
            $query,
            $emitEnabled,
            $userCredentials
        ));
    }

    /**
     * Synchronously deletes a projection
     *
     * @throws Throwable
     */
    public function delete(
        string $name,
        bool $deleteEmittedStreams = false,
        ?UserCredentials $userCredentials = null
    ): void {
        Promise\wait($this->manager->deleteAsync(
            $name,
            $deleteEmittedStreams,
            $userCredentials
        ));
    }

    /**
     * Asynchronously resets a projection
     *
     * @throws Throwable
     */
    public function reset(string $name, ?UserCredentials $userCredentials = null): string
    {
        return Promise\wait($this->manager->resetAsync($name, $userCredentials));
    }
}
