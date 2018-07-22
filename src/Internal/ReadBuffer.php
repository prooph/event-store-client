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

namespace Prooph\EventStoreClient\Internal;

use Amp\Loop;
use Amp\Socket\ClientSocket;
use Generator;
use Prooph\EventStoreClient\Internal\ByteBuffer\Buffer;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Prooph\EventStoreClient\SystemData\TcpFlags;
use Prooph\EventStoreClient\SystemData\TcpPackage;
use Prooph\EventStoreClient\UserCredentials;

/** @internal */
class ReadBuffer
{
    /** @var ClientSocket */
    private $socket;
    /** @var string */
    private $currentMessage;
    /** @var callable */
    private $messageHandler;

    public function __construct(ClientSocket $socket, callable $messageHandler)
    {
        $this->socket = $socket;
        $this->messageHandler = $messageHandler;
    }

    public function startReceivingMessages(): void
    {
        Loop::onReadable($this->socket->getResource(), function (string $watcher): Generator {
            $value = yield $this->socket->read();

            if (null === $value) {
                // stream got closed
                Loop::disable($watcher);

                return;
            }

            if (null !== $this->currentMessage) {
                $value = $this->currentMessage . $value;
            }

            $buffer = Buffer::fromString($value);
            $dataLength = \strlen($value);
            $messageLength = $buffer->readInt32LE(0) + 4; // 4 = size of int32LE

            if ($dataLength === $messageLength) {
                $this->handleMessage($value);
                $this->currentMessage = null;
            } elseif ($dataLength > $messageLength) {
                $message = \substr($value, 0, $messageLength);
                $this->handleMessage($message);

                // reset data to next message
                $value = \substr($value, $messageLength, $dataLength);
                $this->currentMessage = $value;
            } else {
                $this->currentMessage = $value;
            }
        });
    }

    private function handleMessage(string $message): void
    {
        $buffer = Buffer::fromString($message);

        $messageLength = $buffer->readInt32LE(0);

        $command = TcpCommand::fromValue($buffer->readInt8(TcpPackage::DataOffset));
        $flags = TcpFlags::fromValue($buffer->readInt8(TcpPackage::DataOffset + TcpPackage::FlagsOffset));
        $correlationId = \bin2hex($buffer->read(TcpPackage::DataOffset + TcpPackage::CorrelationOffset, TcpPackage::AuthOffset - TcpPackage::CorrelationOffset));
        $headerSize = TcpPackage::MandatorySize;
        $credentials = null;

        if ($flags->equals(TcpFlags::authenticated())) {
            $loginLength = 4 + TcpPackage::AuthOffset;

            if (TcpPackage::AuthOffset + 1 + $loginLength + 1 >= $messageLength) {
                throw new \Exception('Login length is too big, it does not fit into TcpPackage');
            }

            $login = $buffer->read(TcpPackage::DataOffset + TcpPackage::AuthOffset + 1, $loginLength);

            $passwordLength = TcpPackage::DataOffset + TcpPackage::AuthOffset + 1 + $loginLength;

            if (TcpPackage::AuthOffset + 1 + $loginLength + 1 + $passwordLength > $messageLength) {
                throw new \Exception('Password length is too big, it does not fit into TcpPackage');
            }

            $password = $buffer->read($passwordLength + 1, $passwordLength);

            $headerSize += 1 + $loginLength + 1 + $passwordLength;

            \var_dump($login, $password); // @todo debug this
            $credentials = new UserCredentials($login, $password);
        }

        $data = $buffer->read(TcpPackage::DataOffset + $headerSize, $messageLength - $headerSize);

        ($this->messageHandler)(new TcpPackage($command, $flags, $correlationId, $data, $credentials));
    }
}
