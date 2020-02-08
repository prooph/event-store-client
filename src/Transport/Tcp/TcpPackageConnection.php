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

namespace Prooph\EventStoreClient\Transport\Tcp;

use function Amp\call;
use Amp\Loop;
use Amp\Promise;
use Amp\Socket\ClientTlsContext;
use function Amp\Socket\connect;
use Amp\Socket\ConnectContext;
use Amp\Socket\ConnectException;
use Amp\Socket\EncryptableSocket;
use Closure;
use Generator;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Exception\PackageFramingException;
use Prooph\EventStoreClient\SystemData\TcpPackage;
use Psr\Log\LoggerInterface as Logger;
use Throwable;

/** @internal */
class TcpPackageConnection
{
    private Logger $log;
    private EndPoint $remoteEndPoint;
    private string $connectionId;
    private bool $ssl;
    private string $targetHost;
    private bool $validateServer;
    private int $timeout;
    private ?EncryptableSocket $connection = null;
    private bool $isClosed = true;
    private Closure $handlePackage;
    private Closure $onError;
    private Closure $connectionEstablished;
    private Closure $connectionClosed;

    private LengthPrefixMessageFramer $framer;

    public function __construct(
        Logger $logger,
        EndPoint $remoteEndPoint,
        string $connectionId,
        bool $ssl,
        string $targetHost,
        bool $validateServer,
        int $timeout,
        Closure $handlePackage,
        Closure $onError,
        Closure $connectionEstablished,
        Closure $connectionClosed
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

    public function remoteEndPoint(): EndPoint
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
                $context = (new ConnectContext())
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
                    (string) $this->remoteEndPoint,
                    $this->connectionId,
                    $e->getMessage()
                ));
                ($this->connectionClosed)($this, $e);
            } catch (Throwable $e) {
                $this->isClosed = true;
                $this->log->debug(\sprintf(
                    'TcpPackageConnection: connection [%s, %s] was closed with error %s',
                    (string) $this->remoteEndPoint,
                    $this->connectionId,
                    $e->getMessage()
                ));
                ($this->connectionClosed)($this, $e);
            }

            $this->log->debug(\sprintf(
                'TcpPackageConnection: connected to [%s, %s]',
                (string) $this->remoteEndPoint,
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
        try {
            $package = TcpPackage::fromRawData($data);
            ($this->handlePackage)($this, $package);
        } catch (Throwable $e) {
            $this->connection->close();
            $message = \sprintf(
                'TcpPackageConnection: [%s, %s]: Error when processing TcpPackage %s: %s. Connection will be closed',
                (string) $this->remoteEndPoint,
                $this->connectionId,
                isset($package) ? $package->command()->name() : '<invalid package>',
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
                        (string) $this->remoteEndPoint,
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
