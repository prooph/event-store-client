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

use Amp\Loop;
use Amp\Promise;
use Amp\Socket\ClientConnectContext;
use Amp\Socket\ClientSocket;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectException;
use Generator;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Exception\PackageFramingException;
use Prooph\EventStoreClient\IpEndPoint;
use Prooph\EventStoreClient\SystemData\TcpPackage;
use Psr\Log\LoggerInterface as Logger;
use Throwable;
use function Amp\call;
use function Amp\Socket\connect;

/** @internal */
class TcpPackageConnection
{
    /** @var Logger */
    private $log;
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
    private $handlePackage;
    /** @var callable */
    private $onError;
    /** @var callable */
    private $connectionEstablished;
    /** @var callable */
    private $connectionClosed;

    /** @var LengthPrefixMessageFramer */
    private $framer;

    public function __construct(
        Logger $logger,
        IpEndPoint $remoteEndPoint,
        string $connectionId,
        bool $ssl,
        string $targetHost,
        bool $validateServer,
        int $timeout,
        callable $handlePackage,
        callable $onError,
        callable $connectionEstablished,
        callable $connectionClosed
    ) {
        if ($ssl && empty($targetHost)) {
            throw new InvalidArgumentException('Target host cannot be empty when using SSL');
        }

        if (empty($connectionId)) {
            throw new InvalidArgumentException('ConnectionId cannot be empty');
        }

        $this->log = $logger;
        $this->remoteEndPoint = $remoteEndPoint;
        $this->connectionId = $connectionId;
        $this->ssl = $ssl;
        $this->targetHost = $targetHost;
        $this->validateServer = $validateServer;
        $this->timeout = $timeout;
        $this->handlePackage = $handlePackage;
        $this->onError = $onError;
        $this->connectionEstablished = $connectionEstablished;
        $this->connectionClosed = $connectionClosed;

        //Setup callback for incoming messages
        $this->framer = new LengthPrefixMessageFramer();
        $this->framer->registerMessageArrivedCallback(function (string $data): void {
            $this->incomingMessageArrived($data);
        });
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
                $context = (new ClientConnectContext())
                    ->withConnectTimeout($this->timeout);

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
            } catch (ConnectException $e) {
                $this->isClosed = true;
                $this->log->debug(\sprintf(
                    'TcpPackageConnection: connection to [%s, %s] failed. Error: %s',
                    $this->remoteEndPoint,
                    $this->connectionId,
                    $e->getMessage()
                ));
                ($this->connectionClosed)($this, $e);
            } catch (Throwable $e) {
                $this->isClosed = true;
                $this->log->debug(\sprintf(
                    'TcpPackageConnection: connection [%s, %s] was closed with error %s',
                    $this->remoteEndPoint,
                    $this->connectionId,
                    $e->getMessage()
                ));
                ($this->connectionClosed)($this, $e);
            }

            $this->log->debug(\sprintf(
                'TcpPackageConnection: connected to [%s, %s]',
                $this->remoteEndPoint,
                $this->connectionId
            ));

            ($this->connectionEstablished)($this);
        });
    }

    public function enqueueSend(TcpPackage $package): void
    {
        Loop::defer(function () use ($package): Generator {
            try {
                yield $this->connection->write($package->asBytes());
            } catch (Throwable $e) {
                ($this->connectionClosed)($this, $e);
            }
        });
    }

    private function incomingMessageArrived(string $data): void
    {
        $valid = false;

        try {
            $package = TcpPackage::fromRawData($data);
            $valid = true;
            ($this->handlePackage)($this, $package);
        } catch (Throwable $e) {
            $this->connection->close();
            $message = \sprintf(
                'TcpPackageConnection: [%s, %s]: Error when processing TcpPackage %s: %s. Connection will be closed',
                $this->remoteEndPoint,
                $this->connectionId,
                $valid ? $package->command()->name() : '<invalid package>',
                $e->getMessage()
            );

            ($this->onError)($this, $e);
            $this->log->debug($message);
        }
    }

    public function startReceiving(): void
    {
        Loop::defer(function (): Generator {
            while (true) {
                $data = yield $this->connection->read();

                if (null === $data) {
                    // stream got closed
                    return;
                }

                try {
                    $this->framer->unFrameData($data);
                } catch (PackageFramingException $exception) {
                    $this->log->error(\sprintf(
                        'TcpPackageConnection: [%s, %s]. Invalid TCP frame received',
                        $this->remoteEndPoint,
                        $this->connectionId
                    ));

                    $this->close();

                    return;
                }
            }
        });
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
}
