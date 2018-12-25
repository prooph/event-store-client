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

namespace ProophTest\EventStoreClient;

use PHPUnit\Framework\TestCase;
use Prooph\EventStore\ClusterSettings;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\GossipSeed;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStoreClient\EventStoreConnectionFactory as Factory;
use Prooph\EventStoreClient\Uri;

final class connection_factory_can extends TestCase
{
    private const CONNECTION_NAME = 'test-conn';
    private const CLUSTER_DNS = 'escluster.net';
    private const CONNECT_TIMEOUT = 1000;
    private const MAX_DISCOVER_ATTEMPTS = 3;
    private const EXTERNAL_GOSSIP_PORT = 2112;

    /** @test */
    public function create_from_uri_with_discover_scheme(): void
    {
        $conn = Factory::createFromUri(
            Uri::fromString('discover://eventstore:2113'),
            null,
            self::CONNECTION_NAME
        );

        $connectionSettings = ConnectionSettings::default();
        $clusterSettings = ClusterSettings::fromClusterDns(
            'eventstore',
            10,
            2113,
            self::CONNECT_TIMEOUT,
            false
        );

        $this->assertSame(self::CONNECTION_NAME, $conn->connectionName());
        $this->assertEquals($clusterSettings, $conn->clusterSettings());
        $this->assertEquals($connectionSettings, $conn->connectionSettings());
    }

    /** @test */
    public function create_from_uri_with_discover_scheme_defaults_to_uri_credentials(): void
    {
        $conn = Factory::createFromUri(
            Uri::fromString('discover://us3r:p$ss@eventstore:2113')
        );

        $this->assertEquals(
            new UserCredentials('us3r', 'p$ss'),
            $conn->connectionSettings()->defaultUserCredentials()
        );
    }

    /** @test */
    public function create_from_uri_with_tcp_scheme(): void
    {
        $conn = Factory::createFromUri(
            Uri::fromString('tcp://eventstore:1113'),
            null,
            self::CONNECTION_NAME
        );

        $connectionSettings = ConnectionSettings::default();

        $this->assertSame(self::CONNECTION_NAME, $conn->connectionName());
        $this->assertEquals($connectionSettings, $conn->connectionSettings());
    }

    /** @test */
    public function create_from_uri_with_tcp_scheme_defaults_to_uri_credentials(): void
    {
        $conn = Factory::createFromUri(
            Uri::fromString('tcp://us3r:p$ss@eventstore:1113')
        );

        $this->assertEquals(
            new UserCredentials('us3r', 'p$ss'),
            $conn->connectionSettings()->defaultUserCredentials()
        );
    }

    /** @test */
    public function not_create_from_uri_with_unknown_scheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown scheme for connection');

        Factory::createFromUri(
            Uri::fromString('unknown://eventstore:1113')
        );
    }

    /** @test */
    public function create_from_uri_with_gossip_seeds(): void
    {
        $connectionSettings = ConnectionSettings
            ::create()
            ->setGossipSeeds($this->getGossipSeeds())
            ->setMaxDiscoverAttempts(self::MAX_DISCOVER_ATTEMPTS)
            ->setGossipTimeout(self::CONNECT_TIMEOUT)
            ->preferRandomNode()
            ->build();

        $clusterSettings = ClusterSettings::fromGossipSeeds(
            $this->getGossipSeeds(),
            self::MAX_DISCOVER_ATTEMPTS,
            self::CONNECT_TIMEOUT,
            true
        );

        $conn = Factory::createFromUri(
            null,
            $connectionSettings,
            self::CONNECTION_NAME
        );

        $this->assertSame(self::CONNECTION_NAME, $conn->connectionName());
        $this->assertSame($connectionSettings, $conn->connectionSettings());
        $this->assertEquals($clusterSettings, $conn->clusterSettings());
    }

    /** @test */
    public function not_create_from_uri_without_arguments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Must specify uri or gossip seeds');

        Factory::createFromUri(null);
    }

    /** @test */
    public function create_from_connection_string(): void
    {
        $conn = Factory::createFromConnectionString(
            'ConnectTo=tcp://admin:changeit@eventstore:1113; HeartBeatTimeout=500',
            null,
            self::CONNECTION_NAME
        );

        $connectionSettings = ConnectionSettings
            ::create()
            ->setHeartbeatTimeout(500)
            ->setDefaultUserCredentials(
                new UserCredentials('admin', 'changeit')
            )
            ->build();

        $this->assertSame(self::CONNECTION_NAME, $conn->connectionName());
        $this->assertEquals($connectionSettings, $conn->connectionSettings());
    }

    /** @test */
    public function create_from_connection_string_with_gossip_seeds(): void
    {
        $conn = Factory::createFromConnectionString(
            'GossipSeeds=192.168.0.2:1111,192.168.0.3:1111; HeartBeatTimeout=500',
            null,
            self::CONNECTION_NAME
        );

        $connectionSettings = ConnectionSettings
            ::create()
            ->setHeartbeatTimeout(500)
            ->setGossipSeeds([
                new GossipSeed(new EndPoint('192.168.0.2', 1111)),
                new GossipSeed(new EndPoint('192.168.0.3', 1111)),
            ])
            ->build();

        $this->assertSame(self::CONNECTION_NAME, $conn->connectionName());
        $this->assertEquals($connectionSettings, $conn->connectionSettings());
    }

    /** @test */
    public function create_from_connection_string_requires_connectto_or_gossip_seeds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Did not find ConnectTo or GossipSeeds in the connection string');

        Factory::createFromConnectionString(
            'ClusterDns=escluster.net; HeartBeatTimeout=500'
        );
    }

    /** @test */
    public function create_from_connection_string_requires_only_one_of_connectto_and_gossip_seeds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Setting ConnectTo as well as GossipSeeds on the connection string is currently not supported');

        Factory::createFromConnectionString(
            'ConnectTo=tcp://eventstore:1113; GossipSeeds=192.168.0.2:1111,192.168.0.3:1111'
        );
    }

    /** @test */
    public function create_from_cluster_settings_with_dns(): void
    {
        $connectionSettings = ConnectionSettings::default();
        $clusterSettings = ClusterSettings::fromClusterDns(
            self::CLUSTER_DNS,
            self::MAX_DISCOVER_ATTEMPTS,
            self::EXTERNAL_GOSSIP_PORT,
            self::CONNECT_TIMEOUT,
            true
        );

        $conn = Factory::createFromClusterSettings(
            $connectionSettings,
            $clusterSettings,
            self::CONNECTION_NAME
        );

        $this->assertSame($clusterSettings, $conn->clusterSettings());
        $this->assertSame(self::CONNECTION_NAME, $conn->connectionName());
        $this->assertSame($connectionSettings, $conn->connectionSettings());
    }

    /** @test */
    public function create_from_cluster_settings_with_gossip_seeds(): void
    {
        $connectionSettings = ConnectionSettings::default();
        $clusterSettings = ClusterSettings::fromGossipSeeds(
            $this->getGossipSeeds(),
            self::MAX_DISCOVER_ATTEMPTS,
            self::CONNECT_TIMEOUT,
            true
        );

        $conn = Factory::createFromClusterSettings(
            $connectionSettings,
            $clusterSettings,
            self::CONNECTION_NAME
        );

        $this->assertSame($clusterSettings, $conn->clusterSettings());
        $this->assertSame(self::CONNECTION_NAME, $conn->connectionName());
        $this->assertSame($connectionSettings, $conn->connectionSettings());
    }

    /** @test */
    public function create_from_settings_with_gossip_seeds(): void
    {
        $connectionSettings = ConnectionSettings
            ::create()
            ->setGossipSeeds(
                $this->getGossipSeeds()
            )
            ->build();

        $conn = Factory::createFromSettings(
            $connectionSettings,
            self::CONNECTION_NAME
        );

        $this->assertSame(self::CONNECTION_NAME, $conn->connectionName());
        $this->assertSame($connectionSettings, $conn->connectionSettings());
    }

    /** @test */
    public function not_create_from_settings_without_gossip_seeds(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Factory::createFromSettings(
            ConnectionSettings::default()
        );
    }

    /** @test */
    public function create_from_endpoint(): void
    {
        $connectionSettings = ConnectionSettings::create()
            ->performOnAnyNode()
            ->setDefaultUserCredentials(
                new UserCredentials('admin', 'changeit')
            )
            ->build();

        $conn = Factory::createFromEndPoint(
            $this->getEndpoint(),
            $connectionSettings,
            self::CONNECTION_NAME
        );

        $this->assertSame(self::CONNECTION_NAME, $conn->connectionName());
        $this->assertSame($connectionSettings, $conn->connectionSettings());
    }

    /** @test */
    public function create_from_endpoint_using_default_settings(): void
    {
        $conn = Factory::createFromEndPoint(
            $this->getEndpoint(),
            null,
            self::CONNECTION_NAME
        );

        $this->assertSame(self::CONNECTION_NAME, $conn->connectionName());
        $this->assertEquals(ConnectionSettings::default(), $conn->connectionSettings());
    }

    /** @return array */
    private function getGossipSeeds(): array
    {
        return [
            new GossipSeed($this->getEndpoint()),
        ];
    }

    /** @return EndPoint */
    private function getEndpoint(): EndPoint
    {
        return new EndPoint('127.0.0.1', 1113);
    }
}
