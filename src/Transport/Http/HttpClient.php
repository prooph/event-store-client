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

namespace Prooph\EventStoreClient\Transport\Http;

use Amp\Http\Client\HttpClient as AmpHttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Interceptor\SetRequestTimeout;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Closure;
use Prooph\EventStore\Transport\Http\HttpMethod;
use Prooph\EventStore\UserCredentials;
use Throwable;

/** @internal  */
class HttpClient
{
    private AmpHttpClient $httpClient;

    public function __construct(int $operationTimeout)
    {
        $builder = new HttpClientBuilder();
        $builder->intercept(new SetRequestTimeout($operationTimeout, $operationTimeout, $operationTimeout));
        $this->httpClient = $builder->build();
    }

    public function get(
        string $url,
        ?UserCredentials $userCredentials,
        Closure $onSuccess,
        Closure $onException
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
        Closure $onSuccess,
        Closure $onException
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
        Closure $onSuccess,
        Closure $onException
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
        Closure $onSuccess,
        Closure $onException
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
        Closure $onSuccess,
        Closure $onException,
        string $hostHeader = ''
    ): void {
        $request = new Request($url, $method);

        if (null !== $userCredentials) {
            $this->addAuthenticationHeader($request, $userCredentials);
        }

        if ('' !== $hostHeader) {
            $request->setHeader('Host', $hostHeader);
        }

        $this->httpClient->request($request)->onResolve(
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
        Closure $onSuccess,
        Closure $onException
    ): void {
        $request = new Request($url, $method);

        if (null !== $userCredentials) {
            $this->addAuthenticationHeader($request, $userCredentials);
        }

        $request->setHeader('Content-Type', $contentType);
        $request->setHeader('Content-Length', (string) \strlen($body));
        $request->setBody($body);

        $this->httpClient->request($request)->onResolve(
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
    ): void {
        $httpAuthentication = \sprintf(
            '%s:%s',
            $userCredentials->username(),
            $userCredentials->password()
        );

        $encodedCredentials = \base64_encode($httpAuthentication);

        $request->setHeader('Authorization', 'Basic ' . $encodedCredentials);
    }
}
