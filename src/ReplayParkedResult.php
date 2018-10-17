<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient;

/** @internal */
class ReplayParkedResult
{
    /** @var string */
    private $correlationId;
    /** @var string */
    private $reason;
    /** @var ReplayParkedStatus */
    private $status;

    public function __construct(string $correlationId, string $reason, ReplayParkedStatus $status)
    {
        $this->correlationId = $correlationId;
        $this->reason = $reason;
        $this->status = $status;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function status(): ReplayParkedStatus
    {
        return $this->status;
    }
}
