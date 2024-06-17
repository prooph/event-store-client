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

namespace Prooph\EventStoreClient\PersistentSubscriptions;

use Amp\DeferredFuture;
use Amp\Http\Client\Response;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\PersistentSubscriptions\PersistentSubscriptionDetails;
use Prooph\EventStore\Transport\Http\EndpointExtensions;
use Prooph\EventStore\Transport\Http\HttpStatusCode;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\Util\Json;
use Prooph\EventStoreClient\Exception\PersistentSubscriptionCommandFailed;
use Prooph\EventStoreClient\Transport\Http\HttpClient;
use Throwable;
use UnexpectedValueException;

/** @internal */
class PersistentSubscriptionsClient
{
    private HttpClient $client;

    private EndpointExtensions $httpSchema;

    public function __construct(int $operationTimeout, bool $tlsTerminatedEndpoint, bool $verifyPeer)
    {
        $this->client = new HttpClient($operationTimeout, $verifyPeer);
        $this->httpSchema = EndpointExtensions::useHttps($tlsTerminatedEndpoint);
    }

    public function describe(
        EndPoint $endPoint,
        string $stream,
        string $subscriptionName,
        ?UserCredentials $userCredentials = null
    ): PersistentSubscriptionDetails {
        $body = $this->sendGet(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/subscriptions/%s/%s/info',
                $stream,
                $subscriptionName
            ),
            $userCredentials,
            HttpStatusCode::Ok
        );

        if ('' === $body) {
            throw new UnexpectedValueException('No content received');
        }

        return PersistentSubscriptionDetails::fromArray(Json::decode($body));
    }

    /**
     * @return PersistentSubscriptionDetails[]
     */
    public function list(
        EndPoint $endPoint,
        ?string $stream = null,
        ?UserCredentials $userCredentials = null
    ): array {
        $formatString = '/subscriptions';

        if (null !== $stream) {
            $formatString .= "/$stream";
        }

        $body = $this->sendGet(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                $formatString
            ),
            $userCredentials,
            HttpStatusCode::Ok
        );

        if ('' === $body) {
            throw new UnexpectedValueException('No content received');
        }

        return \array_map(
            fn (array $entry) => PersistentSubscriptionDetails::fromArray($entry),
            Json::decode($body)
        );
    }

    public function replayParkedMessages(
        EndPoint $endPoint,
        string $stream,
        string $subscriptionName,
        ?UserCredentials $userCredentials = null
    ): void {
        $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/subscriptions/%s/%s/replayParked',
                $stream,
                $subscriptionName
            ),
            '',
            $userCredentials,
            HttpStatusCode::Ok
        );
    }

    private function sendGet(string $url, ?UserCredentials $userCredentials, int $expectedCode): string
    {
        $deferred = new DeferredFuture();

        $this->client->get(
            $url,
            $userCredentials,
            function (Response $response) use ($deferred, $expectedCode, $url): void {
                if ($response->getStatus() === $expectedCode) {
                    $deferred->complete($response->getBody()->buffer());
                } else {
                    $deferred->error(new PersistentSubscriptionCommandFailed(
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
                    $deferred->complete(null);
                } else {
                    $deferred->error(new PersistentSubscriptionCommandFailed(
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
}
