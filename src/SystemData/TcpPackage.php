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

    public static function fromRawData(string $bytes): TcpPackage
    {
        list('m' => $messageLength, 'c' => $command, 'f' => $flags) = \unpack('Vm/Cc/Cf/', $bytes, self::CommandOffset);

        if ($messageLength < self::MandatorySize) {
            throw new InvalidArgumentException('RawData too short, length: ' . $messageLength);
        }

        $headerSize = self::MandatorySize;
        $command = TcpCommand::fromValue($command);
        $flags = TcpFlags::fromValue($flags);
        $login = null;
        $pass = null;

        if (TcpFlags::Authenticated === $flags) {
            list('l' => $loginLen) = \unpack('Cl/', $bytes, self::DataOffset + self::FlagsOffset);
            list('l' => $login) = \unpack('a' . $loginLen . 'l/', $bytes, self::DataOffset + self::FlagsOffset + 1);

            list('p' => $passLen) = \unpack('Cp/', $bytes, self::DataOffset + self::FlagsOffset + 1 + $loginLen);
            list('p' => $pass) = \unpack('a' . $loginLen . 'p/', $bytes, self::DataOffset + self::FlagsOffset + 2 + $loginLen);

            $headerSize += 1 + $loginLen + 1 + $passLen;

            list('c' => $correlationId, 'd' => $data) = \unpack('H32c/a' . ($messageLength - $headerSize) . 'd/', $bytes, self::DataOffset + self::CorrelationOffset + 2 + $loginLen + $passLen);
        } else {
            list('c' => $correlationId, 'd' => $data) = \unpack('H32c/a' . ($messageLength - $headerSize) . 'd/', $bytes, self::DataOffset + self::CorrelationOffset);
        }

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

            return \pack(
                'VCCH32Ca' . $loginLen . 'Ca' . $passLen . 'a' . $dataLen,
                $messageLen + 2 + $loginLen + $passLen,
                $this->command->value(),
                TcpFlags::Authenticated,
                $this->correlationId,
                $loginLen,
                $this->login,
                $passLen,
                $this->pass,
                $this->data
            );
        }

        return \pack(
            'VCCH32a' . $dataLen,
            $messageLen,
            $this->command->value(),
            TcpFlags::None,
            $this->correlationId,
            $this->data
        );
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
