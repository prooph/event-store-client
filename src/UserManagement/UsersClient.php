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
use Prooph\EventStoreClient\Exception\UnexpectedValueException;
use Prooph\EventStoreClient\Exception\UserCommandConflictException;
use Prooph\EventStoreClient\Exception\UserCommandFailedException;
use Prooph\EventStoreClient\Internal\DateTimeUtil;
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
        IpEndPoint $endPoint,
        string $login,
        UserCredentials $userCredentials = null,
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
        IpEndPoint $endPoint,
        string $login,
        UserCredentials $userCredentials = null,
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
        IpEndPoint $endPoint,
        UserCredentials $userCredentials = null,
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

            $data = \json_decode($body, true);

            if (\json_last_error() !== \JSON_ERROR_NONE) {
                $deferred->fail(new UnexpectedValueException(
                    'Could not json decode response from server'
                ));

                return;
            }

            $userDetails = [];

            foreach ($data['data'] as $entry) {
                $links = [];

                foreach ($entry['links'] as $link) {
                    $links[] = new RelLink($link['href'], $link['rel']);
                }

                $userDetails[] = new UserDetails(
                    $entry['loginName'],
                    $entry['fullName'],
                    $entry['groups'],
                    null,
                    $entry['disabled'],
                    $links
                );
            }

            $deferred->resolve($userDetails);
        });

        return $deferred->promise();
    }

    /** @return Promise<UserDetails> */
    public function getCurrentUser(
        IpEndPoint $endPoint,
        UserCredentials $userCredentials = null,
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

            $data = \json_decode($body, true);

            if (\json_last_error() !== \JSON_ERROR_NONE) {
                $deferred->fail(new UnexpectedValueException(
                    'Could not json decode response from server'
                ));

                return;
            }

            $deferred->resolve(new UserDetails(
                $data['data']['loginName'],
                $data['data']['fullName'],
                $data['data']['groups'],
                DateTimeUtil::create($data['data']['dateLastUpdated']),
                $data['data']['disabled'],
                []
            ));
        });

        return $deferred->promise();
    }

    /** @return Promise<UserDetails> */
    public function getUser(
        IpEndPoint $endPoint,
        string $login,
        UserCredentials $userCredentials = null,
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

            $data = \json_decode($body, true);

            if (\json_last_error() !== \JSON_ERROR_NONE) {
                $deferred->fail(new UnexpectedValueException(
                    'Could not json decode response from server'
                ));

                return;
            }

            $links = [];

            foreach ($data['data']['links'] as $link) {
                $links[] = new RelLink($link['href'], $link['rel']);
            }

            $deferred->resolve(new UserDetails(
                $data['data']['loginName'],
                $data['data']['fullName'],
                $data['data']['groups'],
                isset($data['data']['dateLastUpdated'])
                    ? DateTimeUtil::create($data['data']['dateLastUpdated'])
                    : null,
                $data['data']['disabled'],
                $links
            ));
        });

        return $deferred->promise();
    }

    public function createUser(
        IpEndPoint $endPoint,
        UserCreationInformation $newUser,
        UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendPost(
            EndpointExtensions::rawUrlToHttpUrl(
                $endPoint,
                $httpSchema,
                '/users/'
            ),
            \json_encode($newUser),
            $userCredentials,
            HttpStatusCode::CREATED
        );
    }

    public function updateUser(
        IpEndPoint $endPoint,
        string $login,
        UserUpdateInformation $updatedUser,
        UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendPut(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/users/%s',
                $login
            ),
            \json_encode($updatedUser),
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    public function changePassword(
        IpEndPoint $endPoint,
        string $login,
        ChangePasswordDetails $changePasswordDetails,
        UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/users/%s/command/change-password',
                $login
            ),
            \json_encode($changePasswordDetails),
            $userCredentials,
            HttpStatusCode::OK
        );
    }

    public function resetPassword(
        IpEndPoint $endPoint,
        string $login,
        ResetPasswordDetails $resetPasswordDetails,
        UserCredentials $userCredentials = null,
        string $httpSchema = EndpointExtensions::HTTP_SCHEMA
    ): Promise {
        return $this->sendPost(
            EndpointExtensions::formatStringToHttpUrl(
                $endPoint,
                $httpSchema,
                '/users/%s/command/reset-password',
                $login
            ),
            \json_encode($resetPasswordDetails),
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
