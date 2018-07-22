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
interface WritableBuffer
{
    public function write(string $string, int $offset): void;

    public function writeInt8(int $value, int $offset): void;

    public function writeInt16BE(int $value, int $offset): void;

    public function writeInt16LE(int $value, int $offset): void;

    public function writeInt32BE(int $value, int $offset): void;

    public function writeInt32LE(int $value, int $offset): void;
}
