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

namespace Prooph\EventStoreClient\UserManagement;

use Amp\Artax\Response;
use Amp\Deferred;
use Amp\Promise;
use Prooph\EventStoreClient\EndPoint;
use Prooph\EventStoreClient\Exception\JsonException;
use Prooph\EventStoreClient\Exception\UserCommandConflictException;
use Prooph\EventStoreClient\Exception\UserCommandFailedException;
use Prooph\EventStoreClient\Transport\Http\EndpointExtensions;
use Prooph\EventStoreClient\Transport\Http\HttpClient;
use Prooph\EventStoreClient\Transport\Http\HttpStatusCode;
use Prooph\EventStoreClient\UserCredentials;
use Prooph\EventStoreClient\Util\Json;
use Throwable;

/** @internal */
class UsersClient
{
    /** @var HttpClient */
    private $client;
    /** @var int */
    private $operationTimeout;

    public function __construct(int $operationTimeout)
    {
        $this->client = new HttpClient($operationTimeout);
        $this->operationTimeout = $operationTimeout;
    }

    public function enable(
        EndPoint $endPoint,
        string $login,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/users/%s/command/enable',
                $login
            ),
            '',
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    public function disable(
        EndPoint $endPoint,
        string $login,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/users/%s/command/disable',
                $login
            ),
            '',
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    public function delete(
        EndPoint $endPoint,
        string $login,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendDelete(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/users/%s',
                $login
            ),
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    /** @return Promise<UserDetails[]> */
    public function listAll(
        EndPoint $endPoint,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        $deferred = new Deferred();

        $promise = $this->sendGet(
            EndpointExtensions::rawUrlToHttpUrl($endPoint, $httpSchema, '/users/'),
            $userCredentials,
            HttpStatusCode::OK
        );

        $promise->onResolve(function (?Throwable $exception, ?string $body) use ($deferred): void {
            if ($exception) {
                $deferred->fail($exception);

                return;
            }

            try {
                $data = Json::decode($body);
            } catch (JsonException $e) {
                $deferred->fail($e);

                return;
            }

            $userDetails = [];

            foreach ($data['data'] as $entry) {
                $userDetails[] = UserDetails::fromArray($entry);
            }

            $deferred->resolve($userDetails);
        });

        return $deferred->promise();
    }

    /** @return Promise<UserDetails> */
    public function getCurrentUser(
        EndPoint $endPoint,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        $deferred = new Deferred();

        $promise = $this->sendGet(
            EndpointExtensions::rawUrlToHttpUrl(
                $endPoint,
                $httpSchema,
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

            try {
                $data = Json::decode($body);
            } catch (JsonException $e) {
                $deferred->fail($e);

                return;
            }

            $deferred->resolve(UserDetails::fromArray($data['data']));
        });

        return $deferred->promise();
    }

    /** @return Promise<UserDetails> */
    public function getUser(
        EndPoint $endPoint,
        string $login,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        $deferred = new Deferred();

        $promise = $this->sendGet(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/users/%s',
                $login
            ),
            $userCredentials,
            HttpStatusCode::OK
        );

        $promise->onResolve(function (?Throwable $exception, ?string $body) use ($deferred): void {
            if ($exception) {
                $deferred->fail($exception);

                return;
            }

            try {
                $data = Json::decode($body);
            } catch (JsonException $e) {
                $deferred->fail($e);

                return;
            }

            $deferred->resolve(UserDetails::fromArray($data['data']));
        });

        return $deferred->promise();
    }

    public function createUser(
        EndPoint $endPoint,
        UserCreationInformation $newUser,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendPost(
            EndpointExtensions::rawUrlToHttpUrl(
                $endPoint,
                $httpSchema,
                '/users/'
            ),
            Json::encode($newUser),
            $userCredentials,
            HttpStatusCode::CREATED
        );
    }

    public function updateUser(
        EndPoint $endPoint,
        string $login,
        UserUpdateInformation $updatedUser,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendPut(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/users/%s',
                $login
            ),
            Json::encode($updatedUser),
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    public function changePassword(
        EndPoint $endPoint,
        string $login,
        ChangePasswordDetails $changePasswordDetails,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/users/%s/command/change-password',
                $login
            ),
            Json::encode($changePasswordDetails),
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    public function resetPassword(
        EndPoint $endPoint,
        string $login,
        ResetPasswordDetails $resetPasswordDetails,
        ?UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/users/%s/command/reset-password',
                $login
            ),
            Json::encode($resetPasswordDetails),
            $userCredentials,
            HttpStatusCode::OK
        );
    }

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
                    $deferred->resolve($response->getBody());
                } else {
                    $deferred->fail(new UserCommandFailedException(
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
                    $deferred->resolve($response->getBody());
                } else {
                    $deferred->fail(new UserCommandFailedException(
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
                    $deferred->resolve(null);
                } else {
                    $deferred->fail(new UserCommandFailedException(
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
                    $deferred->resolve(null);
                } elseif ($response->getStatus() === HttpStatusCode::CONFLICT) {
                    $deferred->fail(new UserCommandConflictException($response->getStatus(), $response->getReason()));
                } else {
                    $deferred->fail(new UserCommandFailedException(
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
