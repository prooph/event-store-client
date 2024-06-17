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

use Amp\DeferredFuture;
use Amp\Http\Client\Response;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Projections\ProjectionDetails;
use Prooph\EventStore\Projections\ProjectionStatistics;
use Prooph\EventStore\Projections\Query;
use Prooph\EventStore\Projections\State;
use Prooph\EventStore\Transport\Http\EndpointExtensions;
use Prooph\EventStore\Transport\Http\HttpStatusCode;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\Util\Json;
use Prooph\EventStoreClient\Exception\ProjectionCommandConflict;
use Prooph\EventStoreClient\Exception\ProjectionCommandFailed;
use Prooph\EventStoreClient\Transport\Http\HttpClient;
use Throwable;
use UnexpectedValueException;

/** @internal */
class ProjectionsClient
{
    private readonly HttpClient $client;

    private readonly EndpointExtensions $httpSchema;

    public function __construct(int $operationTimeout, bool $tlsTerminatedEndpoint, bool $verifyPeer)
    {
        $this->client = new HttpClient($operationTimeout, $verifyPeer);
        $this->httpSchema = EndpointExtensions::useHttps($tlsTerminatedEndpoint);
    }

    public function enable(
        EndPoint $endPoint,
        string $name,
        ?UserCredentials $userCredentials = null
    ): void {
        $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/projection/%s/command/enable',
                \urlencode($name)
            ),
            '',
            $userCredentials,
            HttpStatusCode::Ok
        );
    }

    public function disable(
        EndPoint $endPoint,
        string $name,
        ?UserCredentials $userCredentials = null
    ): void {
        $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/projection/%s/command/disable',
                \urlencode($name)
            ),
            '',
            $userCredentials,
            HttpStatusCode::Ok
        );
    }

    public function abort(
        EndPoint $endPoint,
        string $name,
        ?UserCredentials $userCredentials = null
    ): void {
        $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/projection/%s/command/abort',
                \urlencode($name)
            ),
            '',
            $userCredentials,
            HttpStatusCode::Ok
        );
    }

    public function createOneTime(
        EndPoint $endPoint,
        string $query,
        string $type,
        ?UserCredentials $userCredentials = null
    ): void {
        $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/projections/onetime?type=%s',
                $type
            ),
            $query,
            $userCredentials,
            HttpStatusCode::Created
        );
    }

    public function createTransient(
        EndPoint $endPoint,
        string $name,
        string $query,
        string $type,
        ?UserCredentials $userCredentials = null
    ): void {
        $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/projections/transient?name=%s&type=%s',
                \urlencode($name),
                $type
            ),
            $query,
            $userCredentials,
            HttpStatusCode::Created
        );
    }

    public function createContinuous(
        EndPoint $endPoint,
        string $name,
        string $query,
        bool $trackEmittedStreams,
        string $type,
        ?UserCredentials $userCredentials = null
    ): void {
        $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/projections/continuous?name=%s&type=%s&emit=1&trackemittedstreams=%d',
                \urlencode($name),
                $type,
                (string) (int) $trackEmittedStreams
            ),
            $query,
            $userCredentials,
            HttpStatusCode::Created
        );
    }

    /** @return list<ProjectionDetails> */
    public function listAll(
        EndPoint $endPoint,
        ?UserCredentials $userCredentials = null
    ): array {
        $body = $this->sendGet(
            EndpointExtensions::rawUrlToHttpUrl($endPoint, $this->httpSchema, '/projections/any'),
            $userCredentials,
            HttpStatusCode::Ok
        );

        if ('' === $body) {
            throw new \UnexpectedValueException('Body cannot be empty');
        }

        $data = Json::decode($body);

        if (null === $data['projections']) {
            return [];
        }

        return \array_map(
            fn (array $entry) => $this->buildProjectionDetails($entry),
            $data['projections']
        );
    }

    /** @return list<ProjectionDetails> */
    public function listOneTime(
        EndPoint $endPoint,
        ?UserCredentials $userCredentials = null
    ): array {
        $body = $this->sendGet(
            EndpointExtensions::rawUrlToHttpUrl($endPoint, $this->httpSchema, '/projections/onetime'),
            $userCredentials,
            HttpStatusCode::Ok
        );

        if ('' === $body) {
            throw new \UnexpectedValueException('Body cannot be empty');
        }

        $data = Json::decode($body);

        if (null === $data['projections']) {
            return [];
        }

        return \array_map(
            fn (array $entry) => $this->buildProjectionDetails($entry),
            $data['projections']
        );
    }

    /** @return list<ProjectionDetails> */
    public function listContinuous(
        EndPoint $endPoint,
        ?UserCredentials $userCredentials = null
    ): array {
        $body = $this->sendGet(
            EndpointExtensions::rawUrlToHttpUrl($endPoint, $this->httpSchema, '/projections/continuous'),
            $userCredentials,
            HttpStatusCode::Ok
        );

        if ('' === $body) {
            throw new \UnexpectedValueException('Body cannot be empty');
        }

        $data = Json::decode($body);

        if (null === $data['projections']) {
            return [];
        }

        return \array_map(
            fn (array $entry) => $this->buildProjectionDetails($entry),
            $data['projections']
        );
    }

    public function getStatus(
        EndPoint $endPoint,
        string $name,
        ?UserCredentials $userCredentials = null
    ): ProjectionDetails {
        $body = $this->sendGet(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/projection/%s',
                $name
            ),
            $userCredentials,
            HttpStatusCode::Ok
        );

        if ('' === $body) {
            throw new UnexpectedValueException('No content received');
        }

        return $this->buildProjectionDetails(Json::decode($body));
    }

    public function getState(
        EndPoint $endPoint,
        string $name,
        ?UserCredentials $userCredentials = null
    ): State {
        $body = $this->sendGet(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/projection/%s/state',
                $name
            ),
            $userCredentials,
            HttpStatusCode::Ok
        );

        if ('' === $body) {
            throw new UnexpectedValueException('No content received');
        }

        return new State(Json::decode($body));
    }

    public function getPartitionState(
        EndPoint $endPoint,
        string $name,
        string $partition,
        ?UserCredentials $userCredentials = null
    ): State {
        $body = $this->sendGet(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/projection/%s/state?partition=%s',
                $name,
                $partition
            ),
            $userCredentials,
            HttpStatusCode::Ok
        );

        if ('' === $body) {
            throw new UnexpectedValueException('No content received');
        }

        return new State(Json::decode($body));
    }

    public function getResult(
        EndPoint $endPoint,
        string $name,
        ?UserCredentials $userCredentials = null
    ): State {
        $body = $this->sendGet(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/projection/%s/result',
                $name
            ),
            $userCredentials,
            HttpStatusCode::Ok
        );

        if ('' === $body) {
            throw new UnexpectedValueException('No content received');
        }

        return new State(Json::decode($body));
    }

    public function getPartitionResult(
        EndPoint $endPoint,
        string $name,
        string $partition,
        ?UserCredentials $userCredentials = null
    ): State {
        $body = $this->sendGet(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/projection/%s/result?partition=%s',
                $name,
                $partition
            ),
            $userCredentials,
            HttpStatusCode::Ok
        );

        if ('' === $body) {
            throw new UnexpectedValueException('No content received');
        }

        return new State(Json::decode($body));
    }

    public function getStatistics(
        EndPoint $endPoint,
        string $name,
        ?UserCredentials $userCredentials = null
    ): ProjectionStatistics {
        $body = $this->sendGet(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/projection/%s/statistics',
                $name
            ),
            $userCredentials,
            HttpStatusCode::Ok
        );

        if ('' === $body) {
            throw new UnexpectedValueException('No content received');
        }

        return $this->buildProjectionStatistics(Json::decode($body));
    }

    public function getQuery(
        EndPoint $endPoint,
        string $name,
        ?UserCredentials $userCredentials = null
    ): Query {
        $body = $this->sendGet(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/projection/%s/query',
                $name
            ),
            $userCredentials,
            HttpStatusCode::Ok
        );

        if ('' === $body) {
            throw new UnexpectedValueException('No content received');
        }

        return new Query($body);
    }

    public function updateQuery(
        EndPoint $endPoint,
        string $name,
        string $query,
        ?bool $emitEnabled = null,
        ?UserCredentials $userCredentials = null
    ): void {
        $url = '/projection/%s/query';

        if (null !== $emitEnabled) {
            $url .= '?emit=' . (int) $emitEnabled;
        }

        $this->sendPut(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                $url,
                $name
            ),
            $query,
            $userCredentials,
            HttpStatusCode::Ok
        );
    }

    public function reset(
        EndPoint $endPoint,
        string $name,
        ?UserCredentials $userCredentials = null
    ): void {
        $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/projection/%s/command/reset',
                $name
            ),
            '',
            $userCredentials,
            HttpStatusCode::Ok
        );
    }

    public function delete(
        EndPoint $endPoint,
        string $name,
        bool $deleteEmittedStreams,
        ?UserCredentials $userCredentials = null
    ): void {
        $this->sendDelete(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/projection/%s?deleteEmittedStreams=%d',
                $name,
                (string) (int) $deleteEmittedStreams
            ),
            $userCredentials,
            HttpStatusCode::Ok
        );
    }

    private function sendGet(
        string $url,
        ?UserCredentials $userCredentials,
        int $expectedCode
    ): string {
        $deferred = new DeferredFuture();

        $this->client->get(
            $url,
            $userCredentials,
            function (Response $response) use ($deferred, $expectedCode, $url): void {
                if ($response->getStatus() === $expectedCode) {
                    $deferred->complete($response->getBody()->buffer());
                } else {
                    $deferred->error(new ProjectionCommandFailed(
                        $response->getStatus(),
                        \sprintf(
                            'Server returned %d (%s) for GET on %s',
                            $response->getStatus(),
                            $response->getReason(),
                            $url
                        )
                    ));
                }
            },
            function (Throwable $exception) use ($deferred): void {
                $deferred->error($exception);
            }
        );

        return $deferred->getFuture()->await();
    }

    private function sendDelete(
        string $url,
        ?UserCredentials $userCredentials,
        int $expectedCode
    ): void {
        $deferred = new DeferredFuture();

        $this->client->delete(
            $url,
            $userCredentials,
            function (Response $response) use ($deferred, $expectedCode, $url): void {
                if ($response->getStatus() === $expectedCode) {
                    $deferred->complete();
                } else {
                    $deferred->error(new ProjectionCommandFailed(
                        $response->getStatus(),
                        \sprintf(
                            'Server returned %d (%s) for DELETE on %s',
                            $response->getStatus(),
                            $response->getReason(),
                            $url
                        )
                    ));
                }
            },
            function (Throwable $exception) use ($deferred): void {
                $deferred->error($exception);
            }
        );

        $deferred->getFuture()->await();
    }

    private function sendPut(
        string $url,
        string $content,
        ?UserCredentials $userCredentials,
        int $expectedCode
    ): void {
        $deferred = new DeferredFuture();

        $this->client->put(
            $url,
            $content,
            'application/json',
            $userCredentials,
            function (Response $response) use ($deferred, $expectedCode, $url): void {
                if ($response->getStatus() === $expectedCode) {
                    $deferred->complete();
                } else {
                    $deferred->error(new ProjectionCommandFailed(
                        $response->getStatus(),
                        \sprintf(
                            'Server returned %d (%s) for PUT on %s',
                            $response->getStatus(),
                            $response->getReason(),
                            $url
                        )
                    ));
                }
            },
            function (Throwable $exception) use ($deferred): void {
                $deferred->error($exception);
            }
        );

        $deferred->getFuture()->await();
    }

    private function sendPost(
        string $url,
        string $content,
        ?UserCredentials $userCredentials,
        int $expectedCode
    ): void {
        $deferred = new DeferredFuture();

        $this->client->post(
            $url,
            $content,
            'application/json',
            $userCredentials,
            function (Response $response) use ($deferred, $expectedCode, $url): void {
                if ($response->getStatus() === $expectedCode) {
                    $deferred->complete();
                } elseif ($response->getStatus() === HttpStatusCode::Conflict) {
                    $deferred->error(new ProjectionCommandConflict($response->getStatus(), $response->getReason()));
                } else {
                    $deferred->error(new ProjectionCommandFailed(
                        $response->getStatus(),
                        \sprintf(
                            'Server returned %d (%s) for POST on %s',
                            $response->getStatus(),
                            $response->getReason(),
                            $url
                        )
                    ));
                }
            },
            function (Throwable $exception) use ($deferred): void {
                $deferred->error($exception);
            }
        );

        $deferred->getFuture()->await();
    }

    private function buildProjectionDetails(array $entry): ProjectionDetails
    {
        return new ProjectionDetails(
            $entry['coreProcessingTime'],
            $entry['version'],
            $entry['epoch'],
            $entry['effectiveName'],
            $entry['writesInProgress'],
            $entry['readsInProgress'],
            $entry['partitionsCached'],
            $entry['status'],
            $entry['stateReason'] ?? null,
            $entry['name'],
            $entry['mode'],
            $entry['position'],
            $entry['progress'],
            $entry['lastCheckpoint'] ?? null,
            $entry['eventsProcessedAfterRestart'],
            $entry['statusUrl'],
            $entry['stateUrl'],
            $entry['resultUrl'],
            $entry['queryUrl'],
            $entry['enableCommandUrl'],
            $entry['disableCommandUrl'],
            $entry['checkpointStatus'] ?? null,
            $entry['bufferedEvents'],
            $entry['writePendingEventsBeforeCheckpoint'],
            $entry['writePendingEventsAfterCheckpoint']
        );
    }

    private function buildProjectionStatistics(array $entry): ProjectionStatistics
    {
        $projections = \array_reduce($entry['projections'], function (array $carrier, array $entry) {
            $carrier[] = $this->buildProjectionDetails($entry);

            return $carrier;
        }, []);

        return new ProjectionStatistics($projections);
    }
}
