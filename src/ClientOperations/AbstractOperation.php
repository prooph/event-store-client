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

namespace Prooph\EventStoreClient\ClientOperations;

use Amp\DeferredFuture;
use Amp\Future;
use Exception;
use Google\Protobuf\Internal\Message;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Exception\NotAuthenticated;
use Prooph\EventStore\Exception\ServerError;
use Prooph\EventStore\Exception\UnexpectedCommand;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\Messages\ClientMessages\NotHandled;
use Prooph\EventStoreClient\Messages\ClientMessages\NotHandled\MasterInfo;
use Prooph\EventStoreClient\Messages\ClientMessages\NotHandled\NotHandledReason;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Prooph\EventStoreClient\SystemData\TcpFlags;
use Prooph\EventStoreClient\SystemData\TcpPackage;
use Psr\Log\LoggerInterface as Logger;
use Throwable;

/**
 * @internal
 * @template TResponse of Message
 * @template TResult
 */
abstract class AbstractOperation implements ClientOperation
{
    /**
     * @param class-string<TResponse> $responseClassName
     */
    public function __construct(
        private readonly Logger $log,
        protected readonly DeferredFuture $deferred,
        protected readonly ?UserCredentials $credentials,
        private readonly TcpCommand $requestCommand, // we need generics
        private readonly TcpCommand $responseCommand,
        /** @var class-string<TResponse> */
        private readonly string $responseClassName
    ) {
    }

    abstract protected function createRequestDto(): Message;

    /** @param TResponse $response */
    abstract protected function inspectResponse(Message $response): InspectionResult;

    /**
     * @param TResponse $response
     * @return TResult
     */
    abstract protected function transformResponse(Message $response);

    public function future(): Future
    {
        return $this->deferred->getFuture();
    }

    public function createNetworkPackage(string $correlationId): TcpPackage
    {
        $login = null;
        $pass = null;

        if ($this->credentials) {
            $login = $this->credentials->username();
            $pass = $this->credentials->password();
        }

        return new TcpPackage(
            $this->requestCommand,
            $this->credentials ? TcpFlags::Authenticated : TcpFlags::None,
            $correlationId,
            $this->createRequestDto()->serializeToString(),
            $login,
            $pass
        );
    }

    public function inspectPackage(TcpPackage $package): InspectionResult
    {
        if ($package->command() === $this->responseCommand) {
            $responseMessage = new $this->responseClassName();
            $responseMessage->mergeFromString($package->data());

            return $this->inspectResponse($responseMessage);
        }

        return match ($package->command()) {
            TcpCommand::NotAuthenticatedException => $this->inspectNotAuthenticated($package),
            TcpCommand::BadRequest => $this->inspectBadRequest($package),
            TcpCommand::NotHandled => $this->inspectNotHandled($package),
            default => $this->inspectUnexpectedCommand($package, $this->responseCommand),
        };
    }

    protected function succeed(Message $response): void
    {
        try {
            $result = $this->transformResponse($response);
        } catch (Exception $e) {
            $this->deferred->error($e);

            return;
        }

        $this->deferred->complete($result);
    }

    public function fail(Throwable $exception): void
    {
        $this->deferred->error($exception);
    }

    private function inspectNotAuthenticated(TcpPackage $package): InspectionResult
    {
        $this->fail(new NotAuthenticated());

        return new InspectionResult(InspectionDecision::EndOperation, 'Not authenticated');
    }

    private function inspectBadRequest(TcpPackage $package): InspectionResult
    {
        $this->fail(new ServerError());

        return new InspectionResult(InspectionDecision::EndOperation, 'Bad request');
    }

    private function inspectNotHandled(TcpPackage $package): InspectionResult
    {
        $message = new NotHandled();
        $message->mergeFromString($package->data());

        switch ($message->getReason()) {
            case NotHandledReason::NotReady:
                return new InspectionResult(InspectionDecision::Retry, 'Not handled: not ready');
            case NotHandledReason::TooBusy:
                return new InspectionResult(InspectionDecision::Retry, 'Not handled: too busy');
            case NotHandledReason::NotMaster:
                $masterInfo = new MasterInfo();
                $masterInfo->mergeFromString($message->getAdditionalInfo());

                return new InspectionResult(
                    InspectionDecision::Reconnect,
                    'Not handled: not master',
                    new EndPoint(
                        $masterInfo->getExternalTcpAddress(),
                        $masterInfo->getExternalTcpPort()
                    ),
                    new EndPoint(
                        $masterInfo->getExternalSecureTcpAddress(),
                        $masterInfo->getExternalSecureTcpPort()
                    )
                );
            default:
                $this->log->error('Unknown NotHandledReason: ' . $message->getReason());

                return new InspectionResult(InspectionDecision::Retry, 'Not handled: unknown');
        }
    }

    private function inspectUnexpectedCommand(TcpPackage $package, TcpCommand $expectedCommand): InspectionResult
    {
        $this->log->error('Unexpected TcpCommand received');
        $this->log->error(\sprintf(
            'Expected: %s, Actual: %s, Flags: %s, CorrelationId: %s',
            $expectedCommand->name,
            $package->command()->name,
            $package->flags()->name,
            $package->correlationId()
        ));
        $this->log->error(\sprintf(
            'Operation (%s)',
            \get_class($this)
        ));
        $this->log->error('TcpPackage Data Dump (base64):');

        if (empty($package->data())) {
            $this->log->error('--- NO DATA ---');
        } else {
            $this->log->error(\base64_encode($package->data()));
        }

        $exception = UnexpectedCommand::with($package->command(), $expectedCommand);
        $this->fail($exception);

        return new InspectionResult(InspectionDecision::EndOperation, $exception->getMessage());
    }
}
