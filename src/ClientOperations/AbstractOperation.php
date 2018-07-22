<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\ClientOperations;

use Amp\Deferred;
use Amp\Promise;
use Google\Protobuf\Internal\Message;
use Prooph\EventStoreClient\Exception\NotAuthenticatedException;
use Prooph\EventStoreClient\Exception\ServerError;
use Prooph\EventStoreClient\Exception\UnexpectedCommandException;
use Prooph\EventStoreClient\IpEndPoint;
use Prooph\EventStoreClient\Messages\ClientMessages\NotHandled;
use Prooph\EventStoreClient\Messages\ClientMessages\NotHandled\MasterInfo;
use Prooph\EventStoreClient\Messages\ClientMessages\NotHandled\NotHandledReason;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Prooph\EventStoreClient\SystemData\TcpFlags;
use Prooph\EventStoreClient\SystemData\TcpPackage;
use Prooph\EventStoreClient\UserCredentials;
use Psr\Log\LoggerInterface as Logger;
use Throwable;

/** @internal */
abstract class AbstractOperation implements ClientOperation
{
    /** @var Logger */
    private $log;
    /** @var Deferred */
    private $deferred;
    /** @var UserCredentials|null */
    protected $credentials;
    /** @var TcpCommand */
    private $requestCommand;
    /** @var TcpCommand */
    private $responseCommand;
    /** @var string */
    private $responseClassName;

    public function __construct(
        Logger $logger,
        Deferred $deferred,
        ?UserCredentials $credentials,
        TcpCommand $requestCommand,
        TcpCommand $responseCommand,
        string $responseClassName
    ) {
        $this->log = $logger;
        $this->deferred = $deferred;
        $this->credentials = $credentials;
        $this->requestCommand = $requestCommand;
        $this->responseCommand = $responseCommand;
        $this->responseClassName = $responseClassName;
    }

    abstract protected function createRequestDto(): Message;

    abstract protected function inspectResponse(Message $response): InspectionResult;

    abstract protected function transformResponse(Message $response);

    public function promise(): Promise
    {
        return $this->deferred->promise();
    }

    public function createNetworkPackage(string $correlationId): TcpPackage
    {
        return new TcpPackage(
            $this->requestCommand,
            $this->credentials ? TcpFlags::authenticated() : TcpFlags::none(),
            $correlationId,
            $this->createRequestDto()->serializeToString(),
            $this->credentials ?? null
        );
    }

    public function inspectPackage(TcpPackage $package): InspectionResult
    {
        if ($package->command()->equals($this->responseCommand)) {
            /** @var Message $responseMessage */
            $responseMessage = new $this->responseClassName();
            $responseMessage->mergeFromString($package->data());

            return $this->inspectResponse($responseMessage);
        }

        switch ($package->command()->value()) {
            case TcpCommand::NotAuthenticatedException:
                return $this->inspectNotAuthenticated($package);
            case TcpCommand::BadRequest:
                return $this->inspectBadRequest($package);
            case TcpCommand::NotHandled:
                return $this->inspectNotHandled($package);
            default:
                return $this->inspectUnexpectedCommand($package, $this->responseCommand);
        }
    }

    protected function succeed(Message $response): void
    {
        try {
            $result = $this->transformResponse($response);
        } catch (Throwable $e) {
            $this->deferred->fail($e);

            return;
        }

        $this->deferred->resolve($result);
    }

    public function fail(Throwable $exception): void
    {
        $this->deferred->fail($exception);
    }

    private function inspectNotAuthenticated(TcpPackage $package): InspectionResult
    {
        $this->fail(new NotAuthenticatedException());

        return new InspectionResult(InspectionDecision::endOperation(), 'Not authenticated');
    }

    private function inspectBadRequest(TcpPackage $package): InspectionResult
    {
        $this->fail(new ServerError());

        return new InspectionResult(InspectionDecision::endOperation(), 'Bad request');
    }

    private function inspectNotHandled(TcpPackage $package): InspectionResult
    {
        /** @var NotHandled $message */
        $message = $package->data();

        switch ($message->getReason()) {
            case NotHandledReason::NotReady:
                return new InspectionResult(InspectionDecision::retry(), 'Not handled: not ready');
            case NotHandledReason::TooBusy:
                return new InspectionResult(InspectionDecision::retry(), 'Not handled: too busy');
            case NotHandledReason::NotMaster:
                $masterInfo = new MasterInfo();
                $masterInfo->mergeFromString($message->getAdditionalInfo());

                return new InspectionResult(
                    InspectionDecision::reconnect(),
                    'Not handled: not master',
                    new IpEndPoint(
                        $masterInfo->getExternalTcpAddress(),
                        $masterInfo->getExternalTcpPort()
                    ),
                    new IpEndPoint(
                        $masterInfo->getExternalSecureTcpAddress(),
                        $masterInfo->getExternalSecureTcpPort()
                    )
                );
            default:
                $this->log->error('Unknown NotHandledReason: ' . $message->getReason());

                return new InspectionResult(InspectionDecision::retry(), 'Not handled: unknown');
        }
    }

    private function inspectUnexpectedCommand(TcpPackage $package, TcpCommand $expectedCommand): InspectionResult
    {
        $this->log->error('Unexpected TcpCommand received');
        $this->log->error(\sprintf(
            'Expected: %s, Actual: %s, Flags: %s, CorrelationId: %s',
            $expectedCommand->name(),
            $package->command()->name(),
            $package->flags(),
            $package->correlationId()
        ));
        $this->log->error('Operation (%s)', \get_class($this));
        $this->log->error('TcpPackage Data Dump:');
        $this->log->error($package->data());

        $exception = UnexpectedCommandException::with($expectedCommand->name(), $package->command()->name());
        $this->fail($exception);

        return new InspectionResult(InspectionDecision::endOperation(), $exception->getMessage());
    }
}
