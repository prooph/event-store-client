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

namespace Prooph\EventStoreClient\SystemData;

use Prooph\EventStore\Exception\InvalidArgumentException;

/**
 * @internal
 *
 * @psalm-immutable
 */
class TcpPackage
{
    public const CommandOffset = 0;

    public const FlagOffset = self::CommandOffset + 1;

    public const CorrelationOffset = self::FlagOffset + 1;

    public const AuthOffset = self::CorrelationOffset + 16;

    public const MandatorySize = self::AuthOffset;

    public const DataOffset = 4;

    private readonly TcpCommand $command;

    private readonly TcpFlags $flags;

    private readonly string $correlationId;

    private readonly string $data;

    private readonly ?string $login;

    private readonly ?string $password;

    /**
     * @psalm-pure
     */
    public static function fromRawData(string $bytes): TcpPackage
    {
        list('m' => $messageLength, 'c' => $command, 'f' => $flags) = \unpack('Vm/Cc/Cf/', $bytes, self::CommandOffset);

        if ($messageLength < self::MandatorySize) {
            throw new InvalidArgumentException('RawData too short, length: ' . $messageLength);
        }

        $headerSize = self::MandatorySize;
        $command = TcpCommand::from($command);
        $flags = TcpFlags::from($flags);
        $login = null;
        $pass = null;

        list('c' => $correlationId) = \unpack('H32c', $bytes, self::DataOffset + self::CorrelationOffset);

        if ($flags === TcpFlags::Authenticated) {
            list('l' => $loginLen) = \unpack('Cl/', $bytes, self::AuthOffset + self::DataOffset);
            list('l' => $login) = \unpack('a' . $loginLen . 'l/', $bytes, self::AuthOffset + self::DataOffset + 1);

            list('p' => $passLen) = \unpack('Cp/', $bytes, self::AuthOffset + self::DataOffset + 1 + $loginLen);
            list('p' => $pass) = \unpack('a' . $passLen . 'p/', $bytes, self::AuthOffset + self::DataOffset + 2 + $loginLen);

            $headerSize += 1 + $loginLen + 1 + $passLen;
        }

        list('d' => $data) = \unpack('a' . ($messageLength - $headerSize) . 'd/', $bytes, self::DataOffset + $headerSize);

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
        if ($flags === TcpFlags::Authenticated) {
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
        $headerSize = self::MandatorySize;
        $messageLen = $headerSize + $dataLen;

        /** @psalm-suppress ImpureMethodCall */
        if ($this->flags === TcpFlags::Authenticated) {
            $loginLen = \strlen((string) $this->login);
            $passLen = \strlen((string) $this->password);

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
                $this->command->value,
                TcpFlags::Authenticated->value,
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
            $this->command->value,
            TcpFlags::None->value,
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
