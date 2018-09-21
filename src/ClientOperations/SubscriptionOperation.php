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

use Prooph\EventStoreClient\SubscriptionDropReason;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\SystemData\TcpPackage;
use Prooph\EventStoreClient\Transport\Tcp\TcpPackageConnection;
use Throwable;

/** @internal  */
interface SubscriptionOperation
{
    public function dropSubscription(
        SubscriptionDropReason $reason,
        ?Throwable $exception = null,
        ?TcpPackageConnection $connection = null
    ): void;

    public function connectionClosed(): void;

    public function inspectPackage(TcpPackage $package): InspectionResult;

    public function subscribe(string $correlationId, TcpPackageConnection $connection): bool;

    public function name(): string;

    public function __toString(): string;
}
