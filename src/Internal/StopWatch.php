<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

/** @internal */
class StopWatch
{
    /** @var int */
    private $started;

    private function __construct(int $started)
    {
        $this->started = $started;
    }

    public static function startNew(): self
    {
        $now = DateTimeUtil::utcNow();
        $started = (int) \floor($now->format('U.u') * 1000);

        return new self($started);
    }

    public function elapsed(): int
    {
        $now = DateTimeUtil::utcNow();
        $timestamp = (int) \floor($now->format('U.u') * 1000);

        return $timestamp - $this->started;
    }
}
