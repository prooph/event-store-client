<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2024 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2024 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\ClientOperations;

use Amp\Future;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpPackage;
use Throwable;

/** @internal */
interface ClientOperation
{
    public function future(): Future;

    public function createNetworkPackage(string $correlationId): TcpPackage;

    public function inspectPackage(TcpPackage $package): InspectionResult;

    public function fail(Throwable $exception): void;

    public function name(): string;

    public function __toString(): string;
}
