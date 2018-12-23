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

namespace Prooph\EventStoreClient\Transport\Http;

use Amp\Artax\Client;
use Amp\Artax\DefaultClient;
use Amp\Artax\Request;
use Amp\Artax\Response;
use Prooph\EventStore\Transport\Http\HttpMethod;
use Prooph\EventStore\UserCredentials;
use Throwable;

/** @internal  */
class HttpClient
{
    /** @var Client */
    private $client;

    public function __construct(int $operationTimeout)
    {
        $this->client = new DefaultClient();
        $this->client->setOption(Client::OP_TRANSFER_TIMEOUT, $operationTimeout);
    }

    public function get(
        string $url,
        ?UserCredentials $userCredentials,
        callable $onSuccess,
        callable $onException
    ): void {
        $this->receive(
            HttpMethod::GET,
            $url,
            $userCredentials,
            $onSuccess,
            $onException
        );
    }

    public function post(
        string $url,
        string $body,
        string $contentType,
        ?UserCredentials $userCredentials,
        callable $onSuccess,
        callable $onException
    ): void {
        $this->send(
            HttpMethod::POST,
            $url,
            $body,
            $contentType,
            $userCredentials,
            $onSuccess,
            $onException
        );
    }

    public function delete(
        string $url,
        ?UserCredentials $userCredentials,
        callable $onSuccess,
        callable $onException
    ): void {
        $this->receive(
            HttpMethod::DELETE,
            $url,
            $userCredentials,
            $onSuccess,
            $onException
        );
    }

    public function put(
        string $url,
        string $body,
        string $contentType,
        ?UserCredentials $userCredentials,
        callable $onSuccess,
        callable $onException
    ): void {
        $this->send(
            HttpMethod::PUT,
            $url,
            $body,
            $contentType,
            $userCredentials,
            $onSuccess,
            $onException
        );
    }

    private function receive(
        string $method,
        string $url,
        ?UserCredentials $userCredentials,
        callable $onSuccess,
        callable $onException,
        string $hostHeader = ''
    ): void {
        $request = new Request($url, $method);

        if (null !== $userCredentials) {
            $request = $this->addAuthenticationHeader($request, $userCredentials);
        }

        if ('' !== $hostHeader) {
            $request = $request->withHeader('Host', $hostHeader);
        }

        $this->client->request($request)->onResolve(
            function (?Throwable $e, ?Response $response) use ($onSuccess, $onException): void {
                if ($e) {
                    $onException($e);
                }

                if ($response) {
                    $onSuccess($response);
                }
            }
        );
    }

    private function send(
        string $method,
        string $url,
        string $body,
        string $contentType,
        ?UserCredentials $userCredentials,
        callable $onSuccess,
        callable $onException
    ): void {
        $request = new Request($url, $method);

        if (null !== $userCredentials) {
            $request = $this->addAuthenticationHeader($request, $userCredentials);
        }

        $request = $request->withHeader('Content-Type', $contentType);
        $request = $request->withHeader('Content-Length', (string) \strlen($body));
        $request = $request->withBody($body);

        $this->client->request($request)->onResolve(
            function (?Throwable $e, ?Response $response) use ($onSuccess, $onException): void {
                if ($e) {
                    $onException($e);
                }

                if ($response) {
                    $onSuccess($response);
                }
            }
        );
    }

    private function addAuthenticationHeader(
        Request $request,
        UserCredentials $userCredentials
    ): Request {
        $httpAuthentication = \sprintf(
            '%s:%s',
            $userCredentials->username(),
            $userCredentials->password()
        );

        $encodedCredentials = \base64_encode($httpAuthentication);

        return $request->withHeader('Authorization', 'Basic ' . $encodedCredentials);
    }
}
