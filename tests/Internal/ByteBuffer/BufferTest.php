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

namespace ProophTest\EventStoreClient\Internal\ByteBuffer;

use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\Internal\ByteBuffer\Buffer;

class BufferTest extends TestCase
{
    /** @test */
    public function trailing_empty_byte(): void
    {
        $buffer = Buffer::withSize(5);
        $buffer->writeInt32LE(0xfeedface, 0);
        $this->assertSame(\pack('Vx', 0xfeedface), (string) $buffer);
    }

    /** @test */
    public function surrounded_empty_byte(): void
    {
        $buffer = Buffer::withSize(9);
        $buffer->writeInt32BE(0xfeedface, 0);
        $buffer->writeInt32BE(0xcafebabe, 5);
        $this->assertSame(\pack('NxN', 0xfeedface, 0xcafebabe), (string) $buffer);
    }

    /** @test */
    public function deny_too_small_buffer(): void
    {
        $buffer = Buffer::withSize(4);
        $buffer->writeInt32BE(0xfeedface, 0);
        $this->expectException(\RuntimeException::class);
        $buffer->writeInt32LE(0xfeedface, 4);
    }

    /** @test */
    public function it_works_with_two_4_byte_integers(): void
    {
        $buffer = Buffer::withSize(8);
        $buffer->writeInt32BE(0xfeedface, 0);
        $buffer->writeInt32LE(0xfeedface, 4);
        $this->assertSame(\pack('NV', 0xfeedface, 0xfeedface), (string) $buffer);
    }

    /** @test */
    public function it_is_writing_string(): void
    {
        $buffer = Buffer::withSize(10);
        $buffer->writeInt32BE(0xcafebabe, 0);
        $buffer->write('please', 4);
        $this->assertSame(\pack('Na6', 0xcafebabe, 'please'), (string) $buffer);
    }

    /** @test */
    public function deny_too_long_integers(): void
    {
        $buffer = Buffer::withSize(12);
        $this->expectException(\InvalidArgumentException::class);
        $buffer->writeInt32BE(0xfeedfacefeed, 0);
    }

    /** @test */
    public function it_keeps_length(): void
    {
        $buffer = Buffer::withSize(8);
        $this->assertEquals(8, $buffer->length());
    }

    /** @test */
    public function write_int8(): void
    {
        $buffer = Buffer::withSize(1);
        $buffer->writeInt8(0xfe, 0);
        $this->assertSame(\pack('C', 0xfe), (string) $buffer);
    }

    /** @test */
    public function write_int16BE(): void
    {
        $buffer = Buffer::withSize(2);
        $buffer->writeInt16BE(0xbabe, 0);
        $this->assertSame(\pack('n', 0xbabe), (string) $buffer);
    }

    /** @test */
    public function write_int16LE(): void
    {
        $buffer = Buffer::withSize(2);
        $buffer->writeInt16LE(0xabeb, 0);
        $this->assertSame(\pack('v', 0xabeb), (string) $buffer);
    }

    /** @test */
    public function write_int32BE(): void
    {
        $buffer = Buffer::withSize(4);
        $buffer->writeInt32BE(0xfeedface, 0);
        $this->assertSame(\pack('N', 0xfeedface), (string) $buffer);
    }

    /** @test */
    public function write_int32LE(): void
    {
        $buffer = Buffer::withSize(4);
        $buffer->writeInt32LE(0xfeedface, 0);
        $this->assertSame(\pack('V', 0xfeedface), (string) $buffer);
    }

    /** @test */
    public function read_buffer_initialize_lenght(): void
    {
        $buffer = Buffer::fromString(\pack('V', 0xfeedface));
        $this->assertEquals(4, $buffer->length());
    }

    /** @test */
    public function read_int8(): void
    {
        $buffer = Buffer::fromString(\pack('C', 0xfe));
        $this->assertSame(0xfe, $buffer->readInt8(0));
    }

    /** @test */
    public function read_int16BE(): void
    {
        $buffer = Buffer::fromString(\pack('n', 0xbabe));
        $this->assertSame(0xbabe, $buffer->readInt16BE(0));
    }

    /** @test */
    public function read_int16LE(): void
    {
        $buffer = Buffer::fromString(\pack('v', 0xabeb));
        $this->assertSame(0xabeb, $buffer->readInt16LE(0));
    }

    /** @test */
    public function read_int32BE(): void
    {
        $buffer = Buffer::fromString(\pack('N', 0xfeedface));
        $this->assertSame(0xfeedface, $buffer->readInt32BE(0));
    }

    /** @test */
    public function read_int32LE(): void
    {
        $buffer = Buffer::fromString(\pack('V', 0xfeedface));
        $this->assertSame(0xfeedface, $buffer->readInt32LE(0));
    }

    /** @test */
    public function it_reads(): void
    {
        $buffer = Buffer::fromString(\pack('a7', 'message'));
        $this->assertSame('message', $buffer->read(0, 7));
    }

    /** @test */
    public function it_does_complex_read(): void
    {
        $buffer = Buffer::fromString(\pack('Na7', 0xfeedface, 'message'));
        $this->assertSame(0xfeedface, $buffer->readInt32BE(0));
        $this->assertSame('message', $buffer->read(4, 7));
    }

    /** @test */
    public function it_is_writing_and_reading_on_the_same_buffer(): void
    {
        $buffer = Buffer::withSize(10);
        $int32be = 0xfeedface;
        $string = 'hello!';
        $buffer->writeInt32BE($int32be, 0);
        $buffer->write($string, 4);
        $this->assertSame($string, $buffer->read(4, 6));
        $this->assertSame($int32be, $buffer->readInt32BE(0));
    }
}
