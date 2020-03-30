<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2020 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2020 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\PersistentSubscriptionSettings;

class create_persistent_subscription_with_too_big_checkpoint_after extends TestCase
{
    /**
     * @test
     */
    public function the_build_fails_with_argument_exception(): Generator
    {
        $this->expectException(InvalidArgumentException::class);

        PersistentSubscriptionSettings::create()->checkPointAfterMilliseconds(25 * 365 * 24 * 60 * 60 * 1000)->build();
    }
}
