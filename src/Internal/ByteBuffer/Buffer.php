<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ByteBuffer;

use SplFixedArray;

/** @internal */
final class Buffer extends AbstractBuffer
{
    /** @var SplFixedArray */
    private $buffer;
    /** @var LengthMap */
    private $lengthMap;

    private function __construct()
    {
        $this->lengthMap = new LengthMap();
    }

    public static function fromString(string $string): Buffer
    {
        $self = new self();
        $self->initializeStructs(\strlen($string), $string);

        return $self;
    }

    public static function withSize(int $size): Buffer
    {
        $self = new self();
        $self->initializeStructs($size, \pack('x' . $size));

        return $self;
    }

    public function __toString(): string
    {
        $buf = '';

        foreach ($this->buffer as $bytes) {
            $buf .= $bytes;
        }

        return $buf;
    }

    public function length(): int
    {
        return $this->buffer->getSize();
    }

    public function write(string $string, int $offset): void
    {
        $length = \strlen($string);
        $this->insert('a' . $length, $string, $offset);
    }

    public function writeInt8(int $value, int $offset): void
    {
        $this->checkForOverSize(0xff, $value);
        $this->insert('C', $value, $offset);
    }

    public function writeInt16BE(int $value, int $offset): void
    {
        $this->checkForOverSize(0xffff, $value);
        $this->insert('n', $value, $offset);
    }

    public function writeInt16LE(int $value, int $offset): void
    {
        $this->checkForOverSize(0xffff, $value);
        $this->insert('v', $value, $offset);
    }

    public function writeInt32BE(int $value, int $offset): void
    {
        $this->checkForOverSize(0xffffffff, $value);
        $this->insert('N', $value, $offset);
    }

    public function writeInt32LE(int $value, int $offset): void
    {
        $this->checkForOverSize(0xffffffff, $value);
        $this->insert('V', $value, $offset);
    }

    public function read(int $offset, int $length): string
    {
        $format = 'a' . $length;

        return $this->extract($format, $offset, $length);
    }

    public function readInt8(int $offset): int
    {
        $format = 'C';

        return $this->extract($format, $offset, $this->lengthMap->lengthFor($format));
    }

    public function readInt16BE($offset): int
    {
        $format = 'n';

        return $this->extract($format, $offset, $this->lengthMap->lengthFor($format));
    }

    public function readInt16LE($offset): int
    {
        $format = 'v';

        return $this->extract($format, $offset, $this->lengthMap->lengthFor($format));
    }

    public function readInt32BE($offset): int
    {
        $format = 'N';

        return $this->extract($format, $offset, $this->lengthMap->lengthFor($format));
    }

    public function readInt32LE($offset): int
    {
        $format = 'V';

        return $this->extract($format, $offset, $this->lengthMap->lengthFor($format));
    }

    private function initializeStructs(int $length, string $content): void
    {
        $this->buffer = new SplFixedArray($length);

        for ($i = 0; $i < $length; $i++) {
            $this->buffer[$i] = $content[$i];
        }
    }

    private function insert(string $format, $value, int $offset)
    {
        $bytes = \pack($format, $value);

        for ($i = 0; $i < \strlen($bytes); $i++) {
            $this->buffer[$offset++] = $bytes[$i];
        }
    }

    /** @return mixed */
    private function extract(string $format, int $offset, int $length)
    {
        $encoded = '';

        for ($i = 0; $i < $length; $i++) {
            $encoded .= $this->buffer->offsetGet($offset + $i);
        }

        list(, $result) = \unpack($format, $encoded);

        return $result;
    }

    private function checkForOverSize(int $expectedMax, int $actual): void
    {
        if ($actual > $expectedMax) {
            throw new \InvalidArgumentException(
                \sprintf('%d exceeded limit of %d', $actual, $expectedMax)
            );
        }
    }
}
