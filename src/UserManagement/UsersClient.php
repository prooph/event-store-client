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

namespace Prooph\EventStoreClient\UserManagement;

use Amp\Artax\Response;
use Amp\Deferred;
use Amp\Promise;
use Prooph\EventStoreClient\Exception\UserCommandConflictException;
use Prooph\EventStoreClient\Exception\UserCommandFailedException;
use Prooph\EventStoreClient\IpEndPoint;
use Prooph\EventStoreClient\Transport\Http\EndpointExtensions;
use Prooph\EventStoreClient\Transport\Http\HttpAsyncClient;
use Prooph\EventStoreClient\Transport\Http\HttpStatusCode;
use Prooph\EventStoreClient\UserCredentials;
use Throwable;

/** @internal */
class UsersClient
{
    /** @var HttpAsyncClient */
    private $client;
    /** @var int */
    private $operationTimeout;

    /**
     * @param int $operationTimeout in milliseconds
     */
    public function __construct(int $operationTimeout)
    {
        $this->client = new HttpAsyncClient($operationTimeout);
        $this->operationTimeout = $operationTimeout;
    }

    public function enable(
        IpEndPoint $endPoint,
        string $login,
        UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HttpSchema
    ): Promise {
        return $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/users/%s/command/enable',
                [$login]
            ),
            '',
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    public function disable(
        IpEndPoint $endPoint,
        string $login,
        UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HttpSchema
    ): Promise {
        return $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/users/%s/command/disable',
                [$login]
            ),
            '',
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    public function delete(
        IpEndPoint $endPoint,
        string $login,
        UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HttpSchema
    ): Promise {
        return $this->sendDelete(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/users/%s',
                [$login]
            ),
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    /*
        public Task<List<UserDetails>> ListAll(EndPoint endPoint, UserCredentials userCredentials = null, string httpSchema = EndpointExtensions.HTTP_SCHEMA)
        {
            return SendGet(endPoint.ToHttpUrl(httpSchema, "/users/"), userCredentials, HttpStatusCode.OK)
                .ContinueWith(x =>
                    {
                        if (x.IsFaulted) throw x.Exception;
                        var r = JObject.Parse(x.Result);
                        return r["data"] != null ? r["data"].ToObject<List<UserDetails>>() : null;
                    });
        }

        public Task<UserDetails> GetCurrentUser(EndPoint endPoint, UserCredentials userCredentials = null, string httpSchema = EndpointExtensions.HTTP_SCHEMA)
        {
            return SendGet(endPoint.ToHttpUrl(httpSchema, "/users/$current"), userCredentials, HttpStatusCode.OK)
                .ContinueWith(x =>
                {
                    if (x.IsFaulted) throw x.Exception;
                    var r = JObject.Parse(x.Result);
                    return r["data"] != null ? r["data"].ToObject<UserDetails>() : null;
                });
        }

        public Task<UserDetails> GetUser(EndPoint endPoint, string login, UserCredentials userCredentials = null, string httpSchema = EndpointExtensions.HTTP_SCHEMA)
        {
            return SendGet(endPoint.ToHttpUrl(httpSchema, "/users/{0}", login), userCredentials, HttpStatusCode.OK)
                .ContinueWith(x =>
                {
                    if (x.IsFaulted) throw x.Exception;
                    var r = JObject.Parse(x.Result);
                    return r["data"] != null ? r["data"].ToObject<UserDetails>() : null;
                });
        }

        public Task CreateUser(EndPoint endPoint, UserCreationInformation newUser,
            UserCredentials userCredentials = null, string httpSchema = EndpointExtensions.HTTP_SCHEMA)
        {
            var userJson = newUser.ToJson();
            return SendPost(endPoint.ToHttpUrl(httpSchema, "/users/"), userJson, userCredentials, HttpStatusCode.Created);
        }

        public Task UpdateUser(EndPoint endPoint, string login, UserUpdateInformation updatedUser,
            UserCredentials userCredentials, string httpSchema = EndpointExtensions.HTTP_SCHEMA)
        {
            return SendPut(endPoint.ToHttpUrl(httpSchema, "/users/{0}", login), updatedUser.ToJson(), userCredentials, HttpStatusCode.OK);
        }

        public Task ChangePassword(EndPoint endPoint, string login, ChangePasswordDetails changePasswordDetails,
            UserCredentials userCredentials, string httpSchema = EndpointExtensions.HTTP_SCHEMA)
        {
            return SendPost(endPoint.ToHttpUrl(httpSchema, "/users/{0}/command/change-password", login), changePasswordDetails.ToJson(), userCredentials, HttpStatusCode.OK);
        }

        public Task ResetPassword(EndPoint endPoint, string login, ResetPasswordDetails resetPasswordDetails,
            UserCredentials userCredentials = null, string httpSchema = EndpointExtensions.HTTP_SCHEMA)
        {
            return SendPost(endPoint.ToHttpUrl(httpSchema, "/users/{0}/command/reset-password", login), resetPasswordDetails.ToJson(), userCredentials, HttpStatusCode.OK);
        }
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
                } elseif ($response->getStatus() === HttpStatusCode::Conflict) {
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
