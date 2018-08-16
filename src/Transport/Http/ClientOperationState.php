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

namespace Prooph\EventStoreClient\Transport\Http;

use Amp\Artax\Request;
use Amp\Artax\Response;

/** @internal */
class ClientOperationState
{
    /** @var Request */
    private $request;
    /** @var callable(Response $response) */
    private $onSuccess;
    /** @var callable(Throwable $exception) */
    private $onError;
    /** @var Response */
    private $response;

    public function __construct(Request $request, callable $onSuccess, callable $onError)
    {
        $this->request = $request;
        $this->onSuccess = $onSuccess;
        $this->onError = $onError;
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function onSuccess(): callable
    {
        return $this->onSuccess;
    }

    public function onError(): callable
    {
        return $this->onError;
    }

    public function response(): Response
    {
        return $this->response;
    }

    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }
}
