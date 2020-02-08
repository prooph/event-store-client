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

use Closure;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Exception\PackageFramingException;
use Prooph\EventStoreClient\SystemData\TcpPackage;

/** @internal */
class LengthPrefixMessageFramer
{
    private int $maxPackageSize;
    private ?string $messageBuffer = null;
    private Closure $receivedHandler;
    private int $packageLength = 0;

    public function __construct(int $maxPackageSize = 64 * 1024 * 1024)
    {
        if ($maxPackageSize < 1) {
            throw new InvalidArgumentException('Max package size must be positive');
        }

        $this->maxPackageSize = $maxPackageSize;
    }

    public function reset(): void
    {
        $this->messageBuffer = null;
        $this->packageLength = 0;
    }

    public function unFrameData(string $data): void
    {
        if (null !== $this->messageBuffer) {
            $data = $this->messageBuffer . $data;
        }

        $dataLength = \strlen($data);

        if ($dataLength < TcpPackage::MANDATORY_SIZE) {
            // message too short, let's wait for more data
            $this->messageBuffer = $data;

            return;
        }

        if (0 === $this->packageLength) {
            list('length' => $this->packageLength) = \unpack('Vlength', \substr($data, 0, 4));
            $this->packageLength += TcpPackage::DATA_OFFSET;
        }

        if ($this->packageLength > $this->maxPackageSize) {
            throw new PackageFramingException(\sprintf(
                'Package size is out of bounds: %d (max: %d). This is likely an '
                . 'exceptionally large message (reading too many things) or there is'
                . 'a problem with the framing if working on a new client',
                $this->packageLength,
                $this->maxPackageSize
            ));
        }

        if ($dataLength === $this->packageLength) {
            ($this->receivedHandler)($data);

            $this->reset();
        } elseif ($dataLength > $this->packageLength) {
            $length = $this->packageLength;
            $message = \substr($data, 0, $length);

            ($this->receivedHandler)($message);

            $this->reset();

            // unframe the rest again
            $this->unFrameData(\substr($data, $length, $dataLength));
        } else {
            $this->messageBuffer = $data;
        }
    }

    public function registerMessageArrivedCallback(Closure $handler): void
    {
        $this->receivedHandler = $handler;
    }
}
