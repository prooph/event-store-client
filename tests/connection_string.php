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

use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\ConnectionString;

class connection_string extends TestCase
{
    /** @test */
    public function can_set_string_value(): void
    {
        $settings = ConnectionString::getConnectionSettings('targethost=testtest');
        $this->assertSame('testtest', $settings->targetHost());
    }

    /** @test */
    public function can_set_bool_value_with_string(): void
    {
        $settings = ConnectionString::getConnectionSettings('verboselogging=true');
        $this->assertTrue($settings->verboseLogging());
    }

    /** @test */
    public function can_set_with_spaces(): void
    {
        $settings = ConnectionString::getConnectionSettings('Verbose Logging=true');
        $this->assertTrue($settings->verboseLogging());
    }

    /** @test */
    public function can_set_int(): void
    {
        $settings = ConnectionString::getConnectionSettings('maxretries=55');
        $this->assertSame(55, $settings->maxRetries());
    }

    /** @test */
    public function can_set_multiple_values(): void
    {
        $settings = ConnectionString::getConnectionSettings('heartbeattimeout=4;maxretries=55');
        $this->assertSame(4.0, $settings->heartbeatTimeout());
        $this->assertSame(55, $settings->maxRetries());
    }

    /** @test */
    public function can_set_mixed_case(): void
    {
        $settings = ConnectionString::getConnectionSettings('heArtbeAtTimeout=4');
        $this->assertSame(4.0, $settings->heartbeatTimeout());
    }

    /** @test */
    public function can_set_gossip_seeds(): void
    {
        $settings = ConnectionString::getConnectionSettings('gossipseeds=111.222.222.111:1111,111.222.222.111:1112,111.222.222.111:1113');
        $this->assertCount(3, $settings->gossipSeeds());
    }

    /** @test */
    public function can_set_user_credentials(): void
    {
        $settings = ConnectionString::getConnectionSettings('DefaultUserCredentials=foo:bar');
        $this->assertSame('foo', $settings->defaultUserCredentials()->username());
        $this->assertSame('bar', $settings->defaultUserCredentials()->password());
    }
}
