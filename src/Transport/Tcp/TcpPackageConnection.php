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

namespace Prooph\EventStoreClient\Transport\Tcp;

use Amp\ByteStream\ClosedException;
use Amp\Promise;
use Amp\Socket\ClientConnectContext;
use Amp\Socket\ClientSocket;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectException;
use Generator;
use Prooph\EventStoreClient\Internal\ByteBuffer\Buffer;
use Prooph\EventStoreClient\Internal\ReadBuffer;
use Prooph\EventStoreClient\IpEndPoint;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Prooph\EventStoreClient\SystemData\TcpFlags;
use Prooph\EventStoreClient\SystemData\TcpPackage;
use Prooph\EventStoreClient\UserCredentials;
use Ramsey\Uuid\Uuid;
use function Amp\call;
use function Amp\Socket\connect;

/** @internal */
class TcpPackageConnection
{
    /** @var IpEndPoint */
    private $remoteEndPoint;
    /** @var string */
    private $connectionId;
    /** bool */
    private $ssl;
    /** @var string */
    private $targetHost;
    /** @var bool */
    private $validateServer;
    /** @var int */
    private $timeout;
    /** @var ClientSocket */
    private $connection;
    /** @var bool */
    private $isClosed = true;
    /** @var callable */
    private $tcpPackageMessageHandler;
    /** @var callable */
    private $tcpConnectionErrorMessageHandler;
    /** @var callable */
    private $tcpConnectionEstablishedMessageHandler;
    /** @var callable */
    private $tcpConnectionClosedMessageHandler;

    public function __construct(
        IpEndPoint $remoteEndPoint,
        string $connectionId,
        bool $ssl,
        string $targetHost,
        bool $validateServer,
        int $timeout,
        callable $tcpPackageMessageHandler,
        callable $tcpConnectionErrorMessageHandler,
        callable $tcpConnectionEstablishedMessageHandler,
        callable $tcpConnectionClosedMessageHandler
    ) {
        $this->remoteEndPoint = $remoteEndPoint;
        $this->connectionId = $connectionId;
        $this->ssl = $ssl;
        $this->targetHost = $targetHost;
        $this->validateServer = $validateServer;
        $this->timeout = $timeout;
        $this->tcpPackageMessageHandler = $tcpPackageMessageHandler;
        $this->tcpConnectionErrorMessageHandler = $tcpConnectionErrorMessageHandler;
        $this->tcpConnectionEstablishedMessageHandler = $tcpConnectionEstablishedMessageHandler;
        $this->tcpConnectionClosedMessageHandler = $tcpConnectionClosedMessageHandler;
    }

    public function remoteEndPoint(): IpEndPoint
    {
        return $this->remoteEndPoint;
    }

    public function connectionId(): string
    {
        return $this->connectionId;
    }

    public function connectAsync(): Promise
    {
        return call(function (): Generator {
            try {
                $context = (new ClientConnectContext())->withConnectTimeout($this->timeout);
                $uri = \sprintf('tcp://%s:%s', $this->remoteEndPoint->host(), $this->remoteEndPoint->port());
                $this->connection = yield connect($uri, $context);

                if ($this->ssl) {
                    $tlsContext = (new ClientTlsContext())->withPeerName($this->targetHost);

                    if ($this->validateServer) {
                        $tlsContext = $tlsContext->withPeerVerification();
                    }

                    yield $this->connection->enableCrypto($tlsContext);
                }

                $this->isClosed = false;

                ($this->tcpConnectionEstablishedMessageHandler)($this);
            } catch (ConnectException $e) {
                $this->isClosed = true;
                ($this->tcpConnectionClosedMessageHandler)($this, $e);
            } catch (\Throwable $e) {
                $this->isClosed = true;
                ($this->tcpConnectionClosedMessageHandler)($this, $e);
            }
        });
    }

    public function compose(
        TcpCommand $command,
        string $data = null,
        string $correlationId = null,
        UserCredentials $credentials = null
    ): TcpPackage {
        if (null === $correlationId) {
            $correlationId = $this->createCorrelationId();
        }

        return new TcpPackage(
            $command,
            $credentials ? TcpFlags::authenticated() : TcpFlags::none(),
            $correlationId,
            $data,
            $credentials
        );
    }

    public function sendAsync(TcpPackage $package): Promise
    {
        try {
            return $this->connection->write($this->encode($package));
        } catch (ClosedException $e) {
            ($this->tcpConnectionClosedMessageHandler)($this, $e);
        }
    }

    public function startReceiving(): void
    {
        $messageHandler = function (TcpPackage $package): void {
            ($this->tcpPackageMessageHandler)($this, $package);
        };

        $readBuffer = new ReadBuffer($this->connection, $messageHandler);
        $readBuffer->startReceivingMessages();
    }

    public function close(): void
    {
        if ($this->connection) {
            $this->connection->close();
        }
    }

    public function isClosed(): bool
    {
        return $this->isClosed;
    }

    private function encode(TcpPackage $package): string
    {
        $messageLength = TcpOffset::HeaderLength;

        $credentials = $package->credentials();
        $doAuthorization = $credentials ? true : false;
        $authorizationLength = 0;

        if ($doAuthorization) {
            $authorizationLength = 1 + \strlen($credentials->username()) + 1 + \strlen($credentials->password());
        }

        $dataToSend = $package->data();

        if ($dataToSend) {
            $messageLength += \strlen($dataToSend);
        }

        $wholeMessageLength = $messageLength + $authorizationLength + TcpOffset::Int32Length;

        $buffer = Buffer::withSize($wholeMessageLength);
        $buffer->writeInt32LE($messageLength + $authorizationLength, 0);
        $buffer->writeInt8($package->command()->value(), TcpOffset::MessageTypeOffset);
        $buffer->writeInt8(($doAuthorization ? TcpFlags::Authenticated : TcpFlags::None), TcpOffset::FlagOffset);
        $buffer->write(\pack('H*', $package->correlationId()), TcpOffset::CorrelationIdOffset);

        if ($doAuthorization) {
            $usernameLength = \strlen($credentials->username());
            $passwordLength = \strlen($credentials->password());

            $buffer->writeInt8($usernameLength, TcpOffset::DataOffset);
            $buffer->write($credentials->username(), TcpOffset::DataOffset + 1);
            $buffer->writeInt8($passwordLength, TcpOffset::DataOffset + 1 + $usernameLength);
            $buffer->write($credentials->password(), TcpOffset::DataOffset + 1 + $usernameLength + 1);
        }

        if ($dataToSend) {
            $buffer->write($dataToSend, TcpOffset::DataOffset + $authorizationLength);
        }

        return $buffer->__toString();
    }

    public function createCorrelationId(): string
    {
        return \str_replace('-', '', Uuid::uuid4()->toString());
    }
}
