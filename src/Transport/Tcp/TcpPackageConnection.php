<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2022 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2022 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\Transport\Tcp;

use Amp\Socket\ClientTlsContext;
use function Amp\Socket\connect;
use Amp\Socket\ConnectContext;
use Amp\Socket\ConnectException;
use Amp\Socket\EncryptableSocket;
use Closure;
use Exception;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Exception\PackageFramingException;
use Prooph\EventStoreClient\SystemData\TcpPackage;
use Psr\Log\LoggerInterface as Logger;
use Revolt\EventLoop;

/** @internal */
class TcpPackageConnection
{
    private readonly string $connectionId;

    private readonly bool $ssl;

    private readonly string $targetHost;

    private ?EncryptableSocket $connection = null;

    private bool $isClosed = true;

    private readonly LengthPrefixMessageFramer $framer;

    public function __construct(
        private readonly Logger $log,
        private readonly EndPoint $remoteEndPoint,
        string $connectionId,
        bool $ssl,
        string $targetHost,
        private readonly bool $validateServer,
        private readonly float $timeout,
        private readonly Closure $handlePackage,
        private readonly Closure $onError,
        private readonly Closure $connectionEstablished,
        private readonly Closure $connectionClosed
    ) {
        if ($ssl && empty($targetHost)) {
            throw new InvalidArgumentException('Target host cannot be empty when using SSL');
        }

        if (empty($connectionId)) {
            throw new InvalidArgumentException('ConnectionId cannot be empty');
        }

        $this->connectionId = $connectionId;
        $this->ssl = $ssl;
        $this->targetHost = $targetHost;

        //Setup callback for incoming messages
        $this->framer = new LengthPrefixMessageFramer(function (string $data): void {
            $this->incomingMessageArrived($data);
        });
    }

    /** @psalm-mutation-free */
    public function remoteEndPoint(): EndPoint
    {
        return $this->remoteEndPoint;
    }

    /** @psalm-mutation-free */
    public function connectionId(): string
    {
        return $this->connectionId;
    }

    public function connect(): void
    {
        try {
            $context = (new ConnectContext())
                ->withConnectTimeout($this->timeout);

            $uri = \sprintf('tcp://%s:%s', $this->remoteEndPoint->host(), $this->remoteEndPoint->port());

            if ($this->ssl) {
                $tlsContext = new ClientTlsContext($this->targetHost);

                if ($this->validateServer) {
                    $tlsContext = $tlsContext->withPeerVerification();
                }

                $context = $context->withTlsContext($tlsContext);
            }

            /** @psalm-suppress MixedAssignment */
            $this->connection = connect($uri, $context);

            if ($this->ssl) {
                $this->connection->setupTls();
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
        } catch (Exception $e) {
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
    }

    public function enqueueSend(TcpPackage $package): void
    {
        EventLoop::defer(function () use ($package): void {
            try {
                \assert(null !== $this->connection);

                $this->connection->write($package->asBytes());
            } catch (Exception $e) {
                ($this->connectionClosed)($this, $e);
            }
        });
    }

    private function incomingMessageArrived(string $data): void
    {
        $package = TcpPackage::fromRawData($data);

        try {
            ($this->handlePackage)($this, $package);
        } catch (Exception $e) {
            \assert(null !== $this->connection);

            $this->connection->close();

            $message = \sprintf(
                'TcpPackageConnection: [%s, %s]: Error when processing TcpPackage %s: %s. Connection will be closed',
                (string) $this->remoteEndPoint,
                $this->connectionId,
                isset($package) ? $package->command()->name : '<invalid package>',
                $e->getMessage()
            );

            ($this->onError)($this, $e);

            $this->log->debug($message);
        }
    }

    public function startReceiving(): void
    {
        EventLoop::defer(function (): void {
            while (true) {
                \assert(null !== $this->connection);

                $data = $this->connection->read();

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
                }
            }
        });
    }

    public function close(): void
    {
        $this->connection?->close();
    }

    public function isClosed(): bool
    {
        return $this->isClosed;
    }
}
