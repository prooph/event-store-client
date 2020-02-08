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

namespace Prooph\EventStoreClient\SystemData;

use Prooph\EventStore\Exception\InvalidArgumentException;

/** @internal */
class TcpPackage
{
    public const COMMAND_OFFSET = 0;
    public const FLAG_OFFSET = self::COMMAND_OFFSET + 1;
    public const CORRELATION_OFFSET = self::FLAG_OFFSET + 1;
    public const AUTH_OFFSET = self::CORRELATION_OFFSET + 16;

    public const MANDATORY_SIZE = self::AUTH_OFFSET;

    public const DATA_OFFSET = 4;

    private TcpCommand $command;
    private TcpFlags $flags;
    private string $correlationId;
    private string $data;
    private ?string $login;
    private ?string $password;

    public static function fromRawData(string $bytes): TcpPackage
    {
        list('m' => $messageLength, 'c' => $command, 'f' => $flags) = \unpack('Vm/Cc/Cf/', $bytes, self::COMMAND_OFFSET);

        if ($messageLength < self::MANDATORY_SIZE) {
            throw new InvalidArgumentException('RawData too short, length: ' . $messageLength);
        }

        $headerSize = self::MANDATORY_SIZE;
        $command = TcpCommand::fromValue($command);
        $flags = TcpFlags::fromValue($flags);
        $login = null;
        $pass = null;

        list('c' => $correlationId) = \unpack('H32c', $bytes, self::DATA_OFFSET + self::CORRELATION_OFFSET);

        if ($flags->equals(TcpFlags::authenticated())) {
            list('l' => $loginLen) = \unpack('Cl/', $bytes, self::AUTH_OFFSET + self::DATA_OFFSET);
            list('l' => $login) = \unpack('a' . $loginLen . 'l/', $bytes, self::AUTH_OFFSET + self::DATA_OFFSET + 1);

            list('p' => $passLen) = \unpack('Cp/', $bytes, self::AUTH_OFFSET + self::DATA_OFFSET + 1 + $loginLen);
            list('p' => $pass) = \unpack('a' . $passLen . 'p/', $bytes, self::AUTH_OFFSET + self::DATA_OFFSET + 2 + $loginLen);

            $headerSize += 1 + $loginLen + 1 + $passLen;
        }

        list('d' => $data) = \unpack('a' . ($messageLength - $headerSize) . 'd/', $bytes, self::DATA_OFFSET + $headerSize);

        return new self($command, $flags, $correlationId, $data, $login, $pass);
    }

    public function __construct(
        TcpCommand $command,
        TcpFlags $flags,
        string $correlationId,
        string $data = '',
        ?string $login = null,
        ?string $password = null
    ) {
        if ($flags->equals(TcpFlags::authenticated())) {
            if (null === $login) {
                throw new InvalidArgumentException('Login not provided for authorized TcpPackage');
            }

            if (null === $password) {
                throw new InvalidArgumentException('Password not provided for authorized TcpPackage');
            }
        } else {
            if (null !== $login) {
                throw new InvalidArgumentException('Login provided for non-authorized TcpPackage');
            }

            if (null !== $password) {
                throw new InvalidArgumentException('Password provided for non-authorized TcpPackage');
            }
        }

        $this->command = $command;
        $this->flags = $flags;
        $this->correlationId = $correlationId;
        $this->data = $data;
        $this->login = $login;
        $this->password = $password;
    }

    public function asBytes(): string
    {
        $dataLen = \strlen($this->data);
        $headerSize = self::MANDATORY_SIZE;
        $messageLen = $headerSize + $dataLen;

        if ($this->flags->equals(TcpFlags::authenticated())) {
            $loginLen = \strlen($this->login);
            $passLen = \strlen($this->password);

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
                TcpFlags::AUTHENTICATED,
                $this->correlationId,
                $loginLen,
                $this->login,
                $passLen,
                $this->password,
                $this->data
            );
        }

        return \pack(
            'VCCH32a' . $dataLen,
            $messageLen,
            $this->command->value(),
            TcpFlags::NONE,
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

    public function password(): ?string
    {
        return $this->password;
    }
}
