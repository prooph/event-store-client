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

namespace Prooph\EventStoreClient;

class CommitEventRecord
{
    /** @var EventRecord */
    private $event;
    /** @var int */
    public $commitPosition;

    /** @internal */
    public function __construct(EventRecord $event, int $commitPosition)
    {
        $this->event = $event;
        $this->commitPosition = $commitPosition;
    }

    public function event(): EventRecord
    {
        return $this->event;
    }

    public function commitPosition(): int
    {
        return $this->commitPosition;
    }
}
