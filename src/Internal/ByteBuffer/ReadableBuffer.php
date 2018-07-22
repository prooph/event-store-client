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
