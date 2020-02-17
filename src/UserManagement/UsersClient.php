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

namespace Prooph\EventStoreClient\UserManagement;

use Amp\Deferred;
use Amp\Http\Client\Response;
use Amp\Promise;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Exception\JsonException;
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
                \urlencode($login)
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
                \urlencode($login)
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
                \urlencode($login)
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

            if (! \is_string($body)) {
                $deferred->fail(new UnexpectedValueException('No content received'));
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

            if (! \is_string($body)) {
                $deferred->fail(new UnexpectedValueException('No content received'));
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

            if (! \is_string($body)) {
                $deferred->fail(new UnexpectedValueException('No content received'));
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
                \urlencode($login)
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
                \urlencode($login)
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
                \urlencode($login)
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
