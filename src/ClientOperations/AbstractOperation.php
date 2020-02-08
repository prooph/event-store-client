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

namespace Prooph\EventStoreClient\ClientOperations;

use Amp\Deferred;
use Amp\Promise;
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

/** @internal */
abstract class AbstractOperation implements ClientOperation
{
    private Logger $log;
    protected Deferred $deferred;
    protected ?UserCredentials $credentials;
    private TcpCommand $requestCommand;
    private TcpCommand $responseCommand;
    private string $responseClassName;

    public function __construct(
        Logger $logger,
        Deferred $deferred,
        ?UserCredentials $credentials,
        TcpCommand $requestCommand,// we need generics
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

    // we need generics
    abstract protected function transformResponse(Message $response);

    public function promise(): Promise
    {
        return $this->deferred->promise();
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
            $this->credentials ? TcpFlags::authenticated() : TcpFlags::none(),
            $correlationId,
            $this->createRequestDto()->serializeToString(),
            $login,
            $pass
        );
    }

    public function inspectPackage(TcpPackage $package): InspectionResult
    {
        if ($package->command()->equals($this->responseCommand)) {
            $responseMessage = new $this->responseClassName();
            $responseMessage->mergeFromString($package->data());

            return $this->inspectResponse($responseMessage);
        }

        switch ($package->command()->value()) {
            case TcpCommand::NOT_AUTHENTICATED_EXCEPTION:
                return $this->inspectNotAuthenticated($package);
            case TcpCommand::BAD_REQUEST:
                return $this->inspectBadRequest($package);
            case TcpCommand::NOT_HANDLED:
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
        $this->fail(new NotAuthenticated());

        return new InspectionResult(InspectionDecision::endOperation(), 'Not authenticated');
    }

    private function inspectBadRequest(TcpPackage $package): InspectionResult
    {
        $this->fail(new ServerError());

        return new InspectionResult(InspectionDecision::endOperation(), 'Bad request');
    }

    private function inspectNotHandled(TcpPackage $package): InspectionResult
    {
        $message = new NotHandled();
        $message->mergeFromString($package->data());

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

        $exception = UnexpectedCommand::with($expectedCommand->name(), $package->command()->name());
        $this->fail($exception);

        return new InspectionResult(InspectionDecision::endOperation(), $exception->getMessage());
    }
}
