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

namespace Prooph\EventStoreClient\ClientOperations;

use Amp\Promise;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpPackage;
use Throwable;

/** @internal */
interface ClientOperation
{
    public function promise(): Promise;

    public function createNetworkPackage(string $correlationId): TcpPackage;

    public function inspectPackage(TcpPackage $package): InspectionResult;

    public function fail(Throwable $exception): void;

    public function name(): string;

    public function __toString(): string;
}
