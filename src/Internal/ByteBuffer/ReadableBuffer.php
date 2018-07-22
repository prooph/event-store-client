<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ByteBuffer;

/** @internal */
interface ReadableBuffer
{
    public function read(int $start, int $end): string;

    public function readInt8(int $offset): int;

    public function readInt16BE(int $offset): int;

    public function readInt16LE(int $offset): int;

    public function readInt32BE(int $offset): int;

    public function readInt32LE(int $offset): int;
}
