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

namespace Prooph\EventStoreClient\UserManagement;

use Amp\DeferredFuture;
use Amp\Http\Client\Response;
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
    private readonly HttpClient $client;

    private readonly EndpointExtensions $httpSchema;

    public function __construct(int $operationTimeout, bool $tlsTerminatedEndpoint, bool $verifyPeer)
    {
        $this->client = new HttpClient($operationTimeout, $verifyPeer);
        $this->httpSchema = EndpointExtensions::useHttps($tlsTerminatedEndpoint);
    }

    public function enable(
        EndPoint $endPoint,
        string $login,
        ?UserCredentials $userCredentials = null
    ): void {
        $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/users/%s/command/enable',
                \urlencode($login)
            ),
            '',
            $userCredentials,
            HttpStatusCode::Ok
        );
    }

    public function disable(
        EndPoint $endPoint,
        string $login,
        ?UserCredentials $userCredentials = null
    ): void {
        $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/users/%s/command/disable',
                \urlencode($login)
            ),
            '',
            $userCredentials,
            HttpStatusCode::Ok
        );
    }

    public function delete(
        EndPoint $endPoint,
        string $login,
        ?UserCredentials $userCredentials = null
    ): void {
        $this->sendDelete(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/users/%s',
                \urlencode($login)
            ),
            $userCredentials,
            HttpStatusCode::Ok
        );
    }

    /**
     * @return list<UserDetails>
     */
    public function listAll(
        EndPoint $endPoint,
        ?UserCredentials $userCredentials = null
    ): array {
        $body = $this->sendGet(
            EndpointExtensions::rawUrlToHttpUrl($endPoint, $this->httpSchema, '/users/'),
            $userCredentials,
            HttpStatusCode::Ok
        );

        if ('' === $body) {
            throw new UnexpectedValueException('No content received');
        }

        $data = Json::decode($body);

        return \array_map(
            fn (array $entry): UserDetails => UserDetails::fromArray($entry),
            $data['data']
        );
    }

    public function getCurrentUser(
        EndPoint $endPoint,
        ?UserCredentials $userCredentials = null
    ): UserDetails {
        $body = $this->sendGet(
            EndpointExtensions::rawUrlToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/users/$current'
            ),
            $userCredentials,
            HttpStatusCode::Ok
        );

        if ('' === $body) {
            throw new UnexpectedValueException('No content received');
        }

        $data = Json::decode($body);

        return UserDetails::fromArray($data['data']);
    }

    public function getUser(
        EndPoint $endPoint,
        string $login,
        ?UserCredentials $userCredentials = null
    ): UserDetails {
        $body = $this->sendGet(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/users/%s',
                \urlencode($login)
            ),
            $userCredentials,
            HttpStatusCode::Ok
        );

        if ('' === $body) {
            throw new UnexpectedValueException('No content received');
        }

        $data = Json::decode($body);

        return UserDetails::fromArray($data['data']);
    }

    public function createUser(
        EndPoint $endPoint,
        UserCreationInformation $newUser,
        ?UserCredentials $userCredentials = null
    ): void {
        $this->sendPost(
            EndpointExtensions::rawUrlToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/users/'
            ),
            Json::encode($newUser),
            $userCredentials,
            HttpStatusCode::Created
        );
    }

    public function updateUser(
        EndPoint $endPoint,
        string $login,
        UserUpdateInformation $updatedUser,
        ?UserCredentials $userCredentials = null
    ): void {
        $this->sendPut(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/users/%s',
                \urlencode($login)
            ),
            Json::encode($updatedUser),
            $userCredentials,
            HttpStatusCode::Ok
        );
    }

    public function changePassword(
        EndPoint $endPoint,
        string $login,
        ChangePasswordDetails $changePasswordDetails,
        ?UserCredentials $userCredentials = null
    ): void {
        $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/users/%s/command/change-password',
                \urlencode($login)
            ),
            Json::encode($changePasswordDetails),
            $userCredentials,
            HttpStatusCode::Ok
        );
    }

    public function resetPassword(
        EndPoint $endPoint,
        string $login,
        ResetPasswordDetails $resetPasswordDetails,
        ?UserCredentials $userCredentials = null
    ): void {
        $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $this->httpSchema,
                '/users/%s/command/reset-password',
                \urlencode($login)
            ),
            Json::encode($resetPasswordDetails),
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
                    $deferred->error(new UserCommandFailed(
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
                    $deferred->complete($response->getBody()->buffer());
                } else {
                    $deferred->error(new UserCommandFailed(
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
                    $deferred->error(new UserCommandFailed(
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
                    $deferred->error(new UserCommandConflict($response->getStatus(), $response->getReason()));
                } else {
                    $deferred->error(new UserCommandFailed(
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
