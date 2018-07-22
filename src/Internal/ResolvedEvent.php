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

namespace Prooph\EventStoreClient\Internal;

use Prooph\EventStoreClient\EventRecord;
use Prooph\EventStoreClient\Position;

interface ResolvedEvent
{
    public function originalEvent(): ?EventRecord;

    public function originalPosition(): ?Position;

    public function originalStreamName(): string;

    public function originalEventNumber(): int;
}
