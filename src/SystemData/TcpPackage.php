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

namespace Prooph\EventStoreClient\SystemData;

use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Exception\UnexpectedValueException;
use Prooph\EventStoreClient\Internal\ByteBuffer\Buffer;

class TcpPackage
{
    public const CommandOffset = 0;
    public const FlagsOffset = self::CommandOffset + 1;
    public const CorrelationOffset = self::FlagsOffset + 1;
    public const AuthOffset = self::CorrelationOffset + 16;

    public const MandatorySize = self::AuthOffset;

    public const DataOffset = 4;

    /** @var TcpCommand */
    private $command;
    /** @var TcpFlags */
    private $flags;
    /** @var string */
    private $correlationId;
    /** @var string */
    private $data;
    /** @var string|null */
    private $login;
    /** @var string|null */
    private $pass;

    public static function fromRawData(string $data): TcpPackage
    {
        $buffer = Buffer::fromString($data);

        $messageLength = $buffer->readInt32LE(0);

        if ($messageLength < self::MandatorySize) {
            throw new InvalidArgumentException('RawData too short, length: ' . $messageLength);
        }

        $command = TcpCommand::fromValue($buffer->readInt8(self::DataOffset));
        $flags = TcpFlags::fromValue($buffer->readInt8(self::DataOffset + self::FlagsOffset));
        $correlationId = \bin2hex($buffer->read(self::DataOffset + self::CorrelationOffset, self::AuthOffset - self::CorrelationOffset));
        $headerSize = self::MandatorySize;

        $login = null;
        $pass = null;

        if ($flags->equals(TcpFlags::authenticated())) {
            $loginLen = self::DataOffset + self::AuthOffset;

            if (self::AuthOffset + 1 + $loginLen + 1 >= $messageLength) {
                throw new UnexpectedValueException('Login length is too big, it does not fit into TcpPackage');
            }

            $login = $buffer->read(self::DataOffset + self::AuthOffset + 1, $loginLen);

            $passLen = self::DataOffset + self::AuthOffset + 1 + $loginLen;

            if (self::AuthOffset + 1 + $loginLen + 1 + $passLen > $messageLength) {
                throw new UnexpectedValueException('Password length is too big, it does not fit into TcpPackage');
            }

            $pass = $buffer->read($passLen + 1, $passLen);

            $headerSize += 1 + $loginLen + 1 + $passLen;
        }

        $data = $buffer->read(self::DataOffset + $headerSize, $messageLength - $headerSize);

        return new self($command, $flags, $correlationId, $data, $login, $pass);
    }

    public function __construct(
        TcpCommand $command,
        TcpFlags $flags,
        string $correlationId,
        string $data = '',
        string $login = null,
        string $pass = null
    ) {
        $this->command = $command;
        $this->flags = $flags;
        $this->correlationId = $correlationId;
        $this->data = $data;
        $this->login = $login;
        $this->pass = $pass;
    }

    public function asBytes(): string
    {
        $dataLen = \strlen($this->data);
        $headerSize = self::MandatorySize;
        $messageLen = $headerSize + $dataLen;

        if ($this->flags->equals(TcpFlags::authenticated())) {
            $loginLen = \strlen($this->login);
            $passLen = \strlen($this->pass);

            if ($loginLen > 255) {
                throw new InvalidArgumentException(\sprintf(
                    'Login length should be less then 256 bytes (but is %d)',
                    $loginLen
                ));
            }

            if ($passLen > 255) {
                throw new InvalidArgumentException(\sprintf(
                    'Password length should be less then 256 bytes (but is %d)',
                    $passLen
                ));
            }

            $buffer = Buffer::withSize($messageLen + 2 + $loginLen + $passLen + self::DataOffset);

            $buffer->writeInt32LE($messageLen + 2 + $loginLen + $passLen, 0);
            $buffer->writeInt8($this->command->value(), self::DataOffset);
            $buffer->writeInt8(TcpFlags::Authenticated, self::DataOffset + self::FlagsOffset);
            $buffer->write(\pack('H*', $this->correlationId), self::DataOffset + self::CorrelationOffset);
            $buffer->writeInt8($loginLen, self::DataOffset + self::AuthOffset);
            $buffer->write($this->login, self::DataOffset + self::AuthOffset + 1);
            $buffer->writeInt8($passLen, self::DataOffset + self::AuthOffset + 1 + $loginLen);
            $buffer->write($this->pass, self::DataOffset + self::AuthOffset + 1 + $loginLen + 1);
            $buffer->write($this->data, self::DataOffset + self::AuthOffset + 2 + $loginLen + $passLen);
        } else {
            $buffer = Buffer::withSize($messageLen + self::DataOffset);
            $buffer->writeInt32LE($messageLen, 0);
            $buffer->writeInt8($this->command->value(), self::DataOffset);
            $buffer->writeInt8(TcpFlags::None, self::DataOffset + self::FlagsOffset);
            $buffer->write(\pack('H*', $this->correlationId), self::DataOffset + self::CorrelationOffset);
            $buffer->write($this->data, self::DataOffset + self::AuthOffset);
        }

        return (string) $buffer;
    }

    public function command(): TcpCommand
    {
        return $this->command;
    }

    public function flags(): TcpFlags
    {
        return $this->flags;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function data(): string
    {
        return $this->data;
    }

    public function login(): ?string
    {
        return $this->login;
    }

    public function pass(): ?string
    {
        return $this->pass;
    }
}
