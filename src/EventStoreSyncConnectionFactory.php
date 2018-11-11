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

namespace Prooph\EventStoreClient;

use Prooph\EventStoreClient\EventStoreSyncConnection as SyncConnection;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Internal\ClusterDnsEndPointDiscoverer;
use Prooph\EventStoreClient\Internal\EventStoreSyncNodeConnection;
use Prooph\EventStoreClient\Internal\SingleEndpointDiscoverer;
use Prooph\EventStoreClient\Internal\StaticEndPointDiscoverer;

class EventStoreSyncConnectionFactory
{
    public static function createFromConnectionString(
        string $connectionString,
        ConnectionSettings $settings = null,
        string $connectionName = ''
    ): SyncConnection {
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
        string $connectionName = ''
    ): SyncConnection {
        $connectionSettings = $connectionSettings ?? ConnectionSettings::default();

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

                return new EventStoreSyncNodeConnection(
                    $connectionSettings,
                    $clusterSettings,
                    $endPointDiscoverer,
                    $connectionName
                );
            }

            if ('tcp' === $scheme) {
                return new EventStoreSyncNodeConnection(
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

            return new EventStoreSyncNodeConnection(
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
        string $connectionName = ''
    ): SyncConnection {
        $settings = $settings ?? ConnectionSettings::default();

        return new EventStoreSyncNodeConnection(
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
        string $connectionName = ''
    ): SyncConnection {
        return self::createFromUri(null, $settings, $connectionName);
    }

    public static function createFromClusterSettings(
        ConnectionSettings $connectionSettings,
        ClusterSettings $clusterSettings,
        string $connectionName = ''
    ): SyncConnection {
        $endPointDiscoverer = new ClusterDnsEndPointDiscoverer(
            $connectionSettings->log(),
            $clusterSettings->clusterDns(),
            $clusterSettings->maxDiscoverAttempts(),
            $clusterSettings->externalGossipPort(),
            $clusterSettings->gossipSeeds(),
            $clusterSettings->gossipTimeout(),
            $clusterSettings->preferRandomNode()
        );

        return new EventStoreSyncNodeConnection(
            $connectionSettings,
            $clusterSettings,
            $endPointDiscoverer,
            $connectionName
        );
    }
}
