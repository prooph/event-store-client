<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ByteBuffer;

/** @internal */
interface WritableBuffer
{
    public function write(string $string, int $offset): void;

    public function writeInt8(int $value, int $offset): void;

    public function writeInt16BE(int $value, int $offset): void;

    public function writeInt16LE(int $value, int $offset): void;

    public function writeInt32BE(int $value, int $offset): void;

    public function writeInt32LE(int $value, int $offset): void;
}
