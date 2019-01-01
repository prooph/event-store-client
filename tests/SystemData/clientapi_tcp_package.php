<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2019 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient\SystemData;

use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\Util\Guid;
use Prooph\EventStoreClient\SystemData\TcpCommand;
use Prooph\EventStoreClient\SystemData\TcpFlags;
use Prooph\EventStoreClient\SystemData\TcpPackage;

class clientapi_tcp_package extends TestCase
{
    /** @test */
    public function should_throw_argument_null_exception_when_created_as_authorized_but_login_not_provided(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TcpPackage(TcpCommand::badRequest(), TcpFlags::authenticated(), Guid::generateAsHex(), '');
    }

    /** @test */
    public function should_throw_argument_null_exception_when_created_as_authorized_but_password_not_provided(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TcpPackage(TcpCommand::badRequest(), TcpFlags::authenticated(), Guid::generateAsHex(), '', 'login');
    }

    /** @test */
    public function should_throw_argument_exception_when_created_as_not_authorized_but_login_is_provided(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TcpPackage(TcpCommand::badRequest(), TcpFlags::none(), Guid::generateAsHex(), '', 'login', null);
    }

    /** @test */
    public function should_throw_argument_exception_when_created_as_not_authorized_but_password_is_provided(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TcpPackage(TcpCommand::badRequest(), TcpFlags::none(), Guid::generateAsHex(), '', null, 'pa$$');
    }

    /** @test */
    public function not_authorized_with_data_should_serialize_and_deserialize_correctly(): void
    {
        $corrId = Guid::generateAsHex();
        $refPkg = new TcpPackage(TcpCommand::badRequest(), TcpFlags::none(), $corrId, 'data');
        $bytes = $refPkg->asBytes();
        $pkg = TcpPackage::fromRawData($bytes);

        $this->assertTrue($pkg->command()->equals(TcpCommand::badRequest()));
        $this->assertTrue($pkg->flags()->equals(TcpFlags::none()));
        $this->assertSame($corrId, $pkg->correlationId());
        $this->assertNull($pkg->login());
        $this->assertNull($pkg->password());
        $this->assertSame('data', $pkg->data());
    }

    /** @test */
    public function not_authorized_with_empty_data_should_serialize_and_deserialize_correctly(): void
    {
        $corrId = Guid::generateAsHex();
        $refPkg = new TcpPackage(TcpCommand::badRequest(), TcpFlags::none(), $corrId);
        $bytes = $refPkg->asBytes();
        $pkg = TcpPackage::fromRawData($bytes);

        $this->assertTrue($pkg->command()->equals(TcpCommand::badRequest()));
        $this->assertTrue($pkg->flags()->equals(TcpFlags::none()));
        $this->assertSame($corrId, $pkg->correlationId());
        $this->assertNull($pkg->login());
        $this->assertNull($pkg->password());
        $this->assertSame('', $pkg->data());
    }

    /** @test */
    public function authorized_with_data_should_serialize_and_deserialize_correctly(): void
    {
        $corrId = Guid::generateAsHex();
        $refPkg = new TcpPackage(TcpCommand::badRequest(), TcpFlags::authenticated(), $corrId, 'data', 'login', 'password');
        $bytes = $refPkg->asBytes();
        $pkg = TcpPackage::fromRawData($bytes);

        $this->assertTrue($pkg->command()->equals(TcpCommand::badRequest()));
        $this->assertTrue($pkg->flags()->equals(TcpFlags::authenticated()));
        $this->assertSame($corrId, $pkg->correlationId());
        $this->assertSame('login', $pkg->login());
        $this->assertSame('password', $pkg->password());
        $this->assertSame('data', $pkg->data());
    }

    /** @test */
    public function authorized_with_empty_data_should_serialize_and_deserialize_correctly(): void
    {
        $corrId = Guid::generateAsHex();
        $refPkg = new TcpPackage(TcpCommand::badRequest(), TcpFlags::authenticated(), $corrId, '', 'login', 'password');
        $bytes = $refPkg->asBytes();
        $pkg = TcpPackage::fromRawData($bytes);

        $this->assertTrue($pkg->command()->equals(TcpCommand::badRequest()));
        $this->assertTrue($pkg->flags()->equals(TcpFlags::authenticated()));
        $this->assertSame($corrId, $pkg->correlationId());
        $this->assertSame('login', $pkg->login());
        $this->assertSame('password', $pkg->password());
        $this->assertSame('', $pkg->data());
    }

    /** @test */
    public function should_throw_argument_exception_on_serialization_when_login_too_long(): void
    {
        $corrId = Guid::generateAsHex();
        $pkg = new TcpPackage(TcpCommand::badRequest(), TcpFlags::authenticated(), $corrId, '', \str_repeat('*', 256), 'password');

        $this->expectException(InvalidArgumentException::class);

        $pkg->asBytes();
    }

    /** @test */
    public function should_throw_argument_exception_on_serialization_when_password_too_long(): void
    {
        $corrId = Guid::generateAsHex();
        $pkg = new TcpPackage(TcpCommand::badRequest(), TcpFlags::authenticated(), $corrId, '', 'login', \str_repeat('*', 256));

        $this->expectException(InvalidArgumentException::class);

        $pkg->asBytes();
    }
}
