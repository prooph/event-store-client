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

namespace Prooph\EventStoreClient\PersistentSubscriptions;

/** @internal */
final class PersistentSubscriptionConnectionDetails
{
    /** @var string */
    private $from;
    /** @var string */
    private $username;
    /** @var float */    
    private $averageItemsPerSecond;
    /** @var int */
    private $totalItems;
    /** @var int */
    private $countSinceLastMeasurement;
    /** @var int */
    private $availableSlots;
    /** @var int */
    private $inFlightMessages;
    
    private function __construct()
    {
    }
    
    public static function fromArray(array $data): self
    {
        $details = new self();

        $details->from = $data['from'];
        $details->username = $data['username'];
        $details->averageItemsPerSecond = $data['averageItemsPerSecond'];
        $details->totalItems = $data['totalItems'];
        $details->countSinceLastMeasurement = $data['countSinceLastMeasurement'];
        $details->availableSlots = $data['availableSlots'];
        $details->inFlightMessages = $data['inFlightMessages'];

        return $details;
    }

    public function from(): string
    {
        return $this->from;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function averageItemsPerSecond(): float
    {
        return $this->averageItemsPerSecond;
    }

    public function totalItems(): int
    {
        return $this->totalItems;
    }

    public function countSinceLastMeasurement(): int
    {
        return $this->countSinceLastMeasurement;
    }

    public function availableSlots(): int
    {
        return $this->availableSlots;
    }

    public function inFlightMessages(): int
    {
        return $this->inFlightMessages;
    }
}
