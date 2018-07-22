<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ByteBuffer;

/** @internal */
class LengthMap
{
    /** @var array */
    private $map = [
        'n' => 2,
        'N' => 4,
        'v' => 2,
        'V' => 4,
        'c' => 1,
        'C' => 1,
    ];

    public function lengthFor($format): int
    {
        if (! isset($this->map)) {
            throw new \InvalidArgumentException('Invalid format "' . $format . '" given');
        }

        return $this->map[$format];
    }
}
