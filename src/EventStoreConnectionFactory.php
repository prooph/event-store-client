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

namespace Prooph\EventStoreClient;

use Prooph\EventStore\Async\EventStoreConnection;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Internal\ClusterDnsEndPointDiscoverer;
use Prooph\EventStoreClient\Internal\EventStoreNodeConnection;
use Prooph\EventStoreClient\Internal\SingleEndpointDiscoverer;
use Prooph\EventStoreClient\Internal\StaticEndPointDiscoverer;

class EventStoreConnectionFactory
{
    public static function createFromConnectionString(
        string $connectionString,
        ?ConnectionSettings $settings = null,
        ?string $connectionName = null
    ): EventStoreConnection {
        $settings = ConnectionString::getConnectionSettings(
            $connectionString,
            $settings ?? ConnectionSettings::default()
        );

        $uri = ConnectionString::getUriFromConnectionString($connectionString);

        if (null === $uri && empty($settings->gossipSeeds())) {
            throw new InvalidArgumentException(
                'Did not find ConnectTo or GossipSeeds in the connection string'
            );
        }

        if (null !== $uri && ! empty($settings->gossipSeeds())) {
            throw new InvalidArgumentException(
                'Setting ConnectTo as well as GossipSeeds on the connection string is currently not supported'
            );
        }

        return self::createFromUri($uri, $settings, $connectionName);
    }

    public static function createFromUri(
        ?Uri $uri,
        ?ConnectionSettings $connectionSettings = null,
        ?string $connectionName = null
    ): EventStoreConnection {
        $connectionSettings ??= ConnectionSettings::default();

        if (null !== $uri) {
            $scheme = \strtolower($uri->scheme());
            $credentials = $uri->userCredentials();

            if (null !== $credentials) {
                $connectionSettings = $connectionSettings->withDefaultCredentials($credentials);
            }

            if ('discover' === $scheme) {
                $clusterSettings = ClusterSettings::fromClusterDns(
                    $uri->host(),
                    $connectionSettings->maxDiscoverAttempts(),
                    $uri->port(),
                    $connectionSettings->gossipTimeout(),
                    $connectionSettings->preferRandomNode()
                );

                $endPointDiscoverer = new ClusterDnsEndPointDiscoverer(
                    $connectionSettings->log(),
                    $clusterSettings->clusterDns(),
                    $clusterSettings->maxDiscoverAttempts(),
                    $clusterSettings->externalGossipPort(),
                    $clusterSettings->gossipSeeds(),
                    $clusterSettings->gossipTimeout(),
                    $clusterSettings->preferRandomNode()
                );

                return new EventStoreNodeConnection(
                    $connectionSettings,
                    $clusterSettings,
                    $endPointDiscoverer,
                    $connectionName
                );
            }

            if ('tcp' === $scheme) {
                return new EventStoreNodeConnection(
                    $connectionSettings,
                    null,
                    new SingleEndpointDiscoverer(
                        $uri,
                        $connectionSettings->useSslConnection()
                    ),
                    $connectionName
                );
            }

            throw new InvalidArgumentException(\sprintf(
                'Unknown scheme for connection \'%s\'',
                $scheme
            ));
        }

        if (! empty($connectionSettings->gossipSeeds())) {
            $clusterSettings = ClusterSettings::fromGossipSeeds(
                $connectionSettings->gossipSeeds(),
                $connectionSettings->maxDiscoverAttempts(),
                $connectionSettings->gossipTimeout(),
                $connectionSettings->preferRandomNode()
            );

            $endPointDiscoverer = new ClusterDnsEndPointDiscoverer(
                $connectionSettings->log(),
                $clusterSettings->clusterDns(),
                $clusterSettings->maxDiscoverAttempts(),
                $clusterSettings->externalGossipPort(),
                $clusterSettings->gossipSeeds(),
                $clusterSettings->gossipTimeout(),
                $clusterSettings->preferRandomNode()
            );

            return new EventStoreNodeConnection(
                $connectionSettings,
                $clusterSettings,
                $endPointDiscoverer,
                $connectionName
            );
        }

        if ('' !== $connectionSettings->clusterDns()) {
            $clusterSettings = ClusterSettings::fromClusterDns(
                $connectionSettings->clusterDns(),
                $connectionSettings->maxDiscoverAttempts(),
                $connectionSettings->externalGossipPort(),
                $connectionSettings->gossipTimeout(),
                $connectionSettings->preferRandomNode()
            );

            $endPointDiscoverer = new ClusterDnsEndPointDiscoverer(
                $connectionSettings->log(),
                $clusterSettings->clusterDns(),
                $clusterSettings->maxDiscoverAttempts(),
                $clusterSettings->externalGossipPort(),
                $clusterSettings->gossipSeeds(),
                $clusterSettings->gossipTimeout(),
                $clusterSettings->preferRandomNode()
            );

            return new EventStoreNodeConnection(
                $connectionSettings,
                $clusterSettings,
                $endPointDiscoverer,
                $connectionName
            );
        }

        throw new InvalidArgumentException('Must specify uri or gossip seeds');
    }

    public static function createFromEndPoint(
        EndPoint $endPoint,
        ?ConnectionSettings $settings = null,
        ?string $connectionName = null
    ): EventStoreConnection {
        $settings ??= ConnectionSettings::default();

        return new EventStoreNodeConnection(
            $settings,
            null,
            new StaticEndPointDiscoverer(
                $endPoint,
                $settings->useSslConnection()
            ),
            $connectionName
        );
    }

    public static function createFromSettings(
        ConnectionSettings $settings,
        ?string $connectionName = null
    ): EventStoreConnection {
        return self::createFromUri(null, $settings, $connectionName);
    }

    public static function createFromClusterSettings(
        ConnectionSettings $connectionSettings,
        ClusterSettings $clusterSettings,
        string $connectionName = ''
    ): EventStoreConnection {
        $endPointDiscoverer = new ClusterDnsEndPointDiscoverer(
            $connectionSettings->log(),
            $clusterSettings->clusterDns(),
            $clusterSettings->maxDiscoverAttempts(),
            $clusterSettings->externalGossipPort(),
            $clusterSettings->gossipSeeds(),
            $clusterSettings->gossipTimeout(),
            $clusterSettings->preferRandomNode()
        );

        return new EventStoreNodeConnection(
            $connectionSettings,
            $clusterSettings,
            $endPointDiscoverer,
            $connectionName
        );
    }
}
