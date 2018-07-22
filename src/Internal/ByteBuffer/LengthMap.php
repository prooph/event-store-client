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
