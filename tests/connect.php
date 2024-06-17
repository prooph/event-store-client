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

namespace ProophTest\EventStoreClient;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket\ConnectException;
use Prooph\EventStore\EndPoint;
use Prooph\EventStoreClient\EventStoreConnectionFactory;

class connect extends AsyncTestCase
{
    private EndPoint $blackhole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->blackhole = new EndPoint('localhost', 9999);
    }

    /**
     * @test
     * @group ignore
     */
    public function should_throw_exception_when_server_is_down(): void
    {
        $this->expectException(ConnectException::class);

        $connection = EventStoreConnectionFactory::createFromEndPoint(
            $this->blackhole
        );

        $connection->connect();
    }
}
