<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2021 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2021 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\UserManagement;

use Amp\Deferred;
use Amp\Http\Client\Response;
use Amp\Promise;
use JsonException;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Exception\UnexpectedValueException;
use Prooph\EventStore\Transport\Http\EndpointExtensions;
use Prooph\EventStore\Transport\Http\HttpStatusCode;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\UserManagement\ChangePasswordDetails;
use Prooph\EventStore\UserManagement\ResetPasswordDetails;
use Prooph\EventStore\UserManagement\UserCreationInformation;
use Prooph\EventStore\UserManagement\UserDetails;
use Prooph\EventStore\UserManagement\UserUpdateInformation;
use Prooph\EventStore\Util\Json;
use Prooph\EventStoreClient\Exception\UserCommandConflict;
use Prooph\EventStoreClient\Exception\UserCommandFailed;
use Prooph\EventStoreClient\Transport\Http\HttpClient;
use Throwable;

/** @internal */
class UsersClient
{
    private HttpClient $client;
    private int $operationTimeout;
    private string $httpSchema;

    public function __construct(int $operationTimeout, bool $tlsTerminatedEndpoint, bool $verifyPeer)
    {
        $this->client = new HttpClient($operationTimeout, $verifyPeer);
        $this->operationTimeout = $operationTimeout;
        $this->httpSchema = $tlsTerminatedEndpoint ? EndpointExtensions::HTTPS_SCHEMA : EndpointExtensions::HTTP_SCHEMA;
    }

    /** @return Promise<void> */
    public function enable(
        EndPoint $endPoint,
        string $login,
        ?UserCredentials $userCredentials = null
    ): Promise {
        return $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/users/%s/command/enable',
                \urlencode($login)
            ),
            '',
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    /** @return Promise<void> */
    public function disable(
        EndPoint $endPoint,
        string $login,
        ?UserCredentials $userCredentials = null
    ): Promise {
        return $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/users/%s/command/disable',
                \urlencode($login)
            ),
            '',
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    /** @return Promise<void> */
    public function delete(
        EndPoint $endPoint,
        string $login,
        ?UserCredentials $userCredentials = null
    ): Promise {
        return $this->sendDelete(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/users/%s',
                \urlencode($login)
            ),
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    /**
     * @return Promise<list<UserDetails>>
     *
     * @psalm-suppress MixedReturnTypeCoercion
     */
    public function listAll(
        EndPoint $endPoint,
        ?UserCredentials $userCredentials = null
    ): Promise {
        $deferred = new Deferred();

        $promise = $this->sendGet(
            EndpointExtensions::rawUrlToHttpUrl($endPoint, $this->httpSchema, '/users/'),
            $userCredentials,
            HttpStatusCode::OK
        );

        $promise->onResolve(function (?Throwable $exception, ?string $body) use ($deferred): void {
            if ($exception) {
                $deferred->fail($exception);

                return;
            }

            if (null === $body) {
                $deferred->fail(new UnexpectedValueException('No content received'));

                return;
            }

            try {
                $data = Json::decode($body);
            } catch (JsonException $e) {
                $deferred->fail($e);

                return;
            }

            /** @psalm-suppress MixedArgument */
            $deferred->resolve(\array_map(
                fn (array $entry): UserDetails => UserDetails::fromArray($entry),
                $data['data']
            ));
        });

        return $deferred->promise();
    }

    /**
     * @return Promise<UserDetails>
     *
     * @psalm-suppress MixedReturnTypeCoercion
     */
    public function getCurrentUser(
        EndPoint $endPoint,
        ?UserCredentials $userCredentials = null
    ): Promise {
        $deferred = new Deferred();

        $promise = $this->sendGet(
            EndpointExtensions::rawUrlToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/users/$current'
            ),
            $userCredentials,
            HttpStatusCode::OK
        );

        $promise->onResolve(function (?Throwable $exception, ?string $body) use ($deferred): void {
            if ($exception) {
                $deferred->fail($exception);

                return;
            }

            if (null === $body) {
                $deferred->fail(new UnexpectedValueException('No content received'));

                return;
            }

            try {
                $data = Json::decode($body);
            } catch (JsonException $e) {
                $deferred->fail($e);

                return;
            }

            /** @psalm-suppress MixedArgument */
            $deferred->resolve(UserDetails::fromArray($data['data']));
        });

        return $deferred->promise();
    }

    /**
     * @return Promise<UserDetails>
     *
     * @psalm-suppress MixedReturnTypeCoercion
     */
    public function getUser(
        EndPoint $endPoint,
        string $login,
        ?UserCredentials $userCredentials = null
    ): Promise {
        $deferred = new Deferred();

        $promise = $this->sendGet(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/users/%s',
                \urlencode($login)
            ),
            $userCredentials,
            HttpStatusCode::OK
        );

        $promise->onResolve(function (?Throwable $exception, ?string $body) use ($deferred): void {
            if ($exception) {
                $deferred->fail($exception);

                return;
            }

            if (null === $body) {
                $deferred->fail(new UnexpectedValueException('No content received'));

                return;
            }

            try {
                $data = Json::decode($body);
            } catch (JsonException $e) {
                $deferred->fail($e);

                return;
            }

            /** @psalm-suppress MixedArgument */
            $deferred->resolve(UserDetails::fromArray($data['data']));
        });

        return $deferred->promise();
    }

    /** @return Promise<void> */
    public function createUser(
        EndPoint $endPoint,
        UserCreationInformation $newUser,
        ?UserCredentials $userCredentials = null
    ): Promise {
        return $this->sendPost(
            EndpointExtensions::rawUrlToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/users/'
            ),
            Json::encode($newUser),
            $userCredentials,
            HttpStatusCode::CREATED
        );
    }

    /** @return Promise<void> */
    public function updateUser(
        EndPoint $endPoint,
        string $login,
        UserUpdateInformation $updatedUser,
        ?UserCredentials $userCredentials = null
    ): Promise {
        return $this->sendPut(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/users/%s',
                \urlencode($login)
            ),
            Json::encode($updatedUser),
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    /** @return Promise<void> */
    public function changePassword(
        EndPoint $endPoint,
        string $login,
        ChangePasswordDetails $changePasswordDetails,
        ?UserCredentials $userCredentials = null
    ): Promise {
        return $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/users/%s/command/change-password',
                \urlencode($login)
            ),
            Json::encode($changePasswordDetails),
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    /** @return Promise<void> */
    public function resetPassword(
        EndPoint $endPoint,
        string $login,
        ResetPasswordDetails $resetPasswordDetails,
        ?UserCredentials $userCredentials = null
    ): Promise {
        return $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/users/%s/command/reset-password',
                \urlencode($login)
            ),
            Json::encode($resetPasswordDetails),
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    /**
     * @return Promise<string>
     *
     * @psalm-suppress MixedReturnTypeCoercion
     */
    private function sendGet(
        string $url,
        ?UserCredentials $userCredentials,
        int $expectedCode
    ): Promise {
        $deferred = new Deferred();

        $this->client->get(
            $url,
            $userCredentials,
            function (Response $response) use ($deferred, $expectedCode, $url): void {
                if ($response->getStatus() === $expectedCode) {
                    $deferred->resolve($response->getBody()->buffer());
                } else {
                    $deferred->fail(new UserCommandFailed(
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
                $deferred->fail($exception);
            }
        );

        return $deferred->promise();
    }

    /**
     * @return Promise<void>
     *
     * @psalm-suppress MixedReturnTypeCoercion
     */
    private function sendDelete(
        string $url,
        ?UserCredentials $userCredentials,
        int $expectedCode
    ): Promise {
        $deferred = new Deferred();

        $this->client->delete(
            $url,
            $userCredentials,
            function (Response $response) use ($deferred, $expectedCode, $url): void {
                if ($response->getStatus() === $expectedCode) {
                    $deferred->resolve($response->getBody()->buffer());
                } else {
                    $deferred->fail(new UserCommandFailed(
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
                $deferred->fail($exception);
            }
        );

        return $deferred->promise();
    }

    /**
     * @return Promise<void>
     *
     * @psalm-suppress MixedReturnTypeCoercion
     */
    private function sendPut(
        string $url,
        string $content,
        ?UserCredentials $userCredentials,
        int $expectedCode
    ): Promise {
        $deferred = new Deferred();

        $this->client->put(
            $url,
            $content,
            'application/json',
            $userCredentials,
            function (Response $response) use ($deferred, $expectedCode, $url): void {
                if ($response->getStatus() === $expectedCode) {
                    $deferred->resolve();
                } else {
                    $deferred->fail(new UserCommandFailed(
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
                $deferred->fail($exception);
            }
        );

        return $deferred->promise();
    }

    /**
     * @return Promise<void>
     *
     * @psalm-suppress MixedReturnTypeCoercion
     */
    private function sendPost(
        string $url,
        string $content,
        ?UserCredentials $userCredentials,
        int $expectedCode
    ): Promise {
        $deferred = new Deferred();

        $this->client->post(
            $url,
            $content,
            'application/json',
            $userCredentials,
            function (Response $response) use ($deferred, $expectedCode, $url): void {
                if ($response->getStatus() === $expectedCode) {
                    $deferred->resolve();
                } elseif ($response->getStatus() === HttpStatusCode::CONFLICT) {
                    $deferred->fail(new UserCommandConflict($response->getStatus(), $response->getReason()));
                } else {
                    $deferred->fail(new UserCommandFailed(
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
                $deferred->fail($exception);
            }
        );

        return $deferred->promise();
    }
}
