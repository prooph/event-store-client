<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Prooph\EventStoreClient\EventStoreAsyncConnection as AsyncConnection;
use Prooph\EventStoreClient\EventStoreSyncConnection as SyncConnection;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Internal\ClusterDnsEndPointDiscoverer;
use Prooph\EventStoreClient\Internal\Consts;
use Prooph\EventStoreClient\Internal\EventStoreAsyncNodeConnection;
use Prooph\EventStoreClient\Internal\EventStoreSyncNodeConnection;
use Prooph\EventStoreClient\Internal\SingleEndpointDiscoverer;
use Prooph\EventStoreClient\Internal\StaticEndPointDiscoverer;

class EventStoreConnectionBuilder
{
    /**
     * Sub-delimiters used in user info, query strings and fragments.
     * @const string
     */
    private const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';
    /**
     * Unreserved characters used in user info, paths, query strings, and fragments.
     * @const string
     */
    private const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~\pL';

    /** @throws \Exception */
    public static function createAsyncFromBuilder(
        string $connectionString = null,
        ConnectionSettingsBuilder $builder = null,
        string $connectionName = ''
    ): AsyncConnection {
        $builder = $builder ?? new ConnectionSettingsBuilder();

        return self::createAsyncFromSettings($connectionString, $builder->build(), $connectionName);
    }

    /** @throws \Exception */
    public static function createAsyncFromSettings(
        string $connectionString = null,
        ConnectionSettings $settings = null,
        string $connectionName = ''
    ): AsyncConnection {
        if (null === $connectionString && (null === $settings || (empty($settings->gossipSeeds()) && empty($settings->clusterDns())))) {
            throw new \Exception('Did not find ConnectTo, ClusterDNS or GossipSeeds in the connection string');
        }

        if (null !== $connectionString && (null === $settings || (empty($settings->gossipSeeds()) && empty($settings->clusterDns())))) {
            throw new \Exception('Setting ConnectTo as well as GossipSeeds and/or ClusterDNS on the connection string is currently not supported');
        }

        if (null !== $connectionString) {
            list($scheme, $host, $port, $user, $pass) = self::parseUri($connectionString);
            $credentials = $user ? new UserCredentials($user, $pass) : null;
            if (null !== $credentials) {
                $settings = new ConnectionSettings(
                    $settings->maxQueueSize(),
                    $settings->maxConcurrentItems(),
                    $settings->maxRetries(),
                    $settings->maxReconnections(),
                    $settings->requireMaster(),
                    $settings->reconnectionDelay(),
                    $settings->operationTimeout(),
                    $settings->operationTimeoutCheckPeriod(),
                    $credentials,
                    $settings->useSslConnection(),
                    $settings->targetHost(),
                    $settings->validateServer(),
                    $settings->failOnNoServerResponse(),
                    $settings->heartbeatInterval(),
                    $settings->heartbeatTimeout(),
                    $settings->clientConnectionTimeout(),
                    $settings->clusterDns(),
                    $settings->gossipSeeds(),
                    $settings->maxDiscoverAttempts(),
                    $settings->externalGossipPort(),
                    $settings->gossipTimeout(),
                    $settings->preferRandomNode()
                );
            }

            if ($scheme === 'discover') {
                return self::createWithClusterDnsEndPointDiscoverer($settings, $connectionName);
            }

            if ($scheme === 'tcp') {
                return self::createWithSingleEndpointDiscoverer(
                    $connectionString,
                    $settings,
                    $connectionName
                );
            }

            throw new \Exception('Unknown scheme for connection');
        }

        if (! empty($settings->gossipSeeds()) || ! empty($settings->clusterDns())) {
            return self::createWithClusterDnsEndPointDiscoverer($settings, $connectionName);
        }

        throw new \Exception('Must specify uri, ClusterDNS or gossip seeds');
    }

    public static function createAsyncFromIpEndPoint(
        IpEndPoint $endPoint,
        ConnectionSettings $settings = null,
        string $connectionName = null
    ): AsyncConnection {
        $settings = $settings ?? ConnectionSettings::default();

        return new EventStoreAsyncNodeConnection(
            $settings,
            null,
            new StaticEndPointDiscoverer($endPoint, $settings->useSslConnection()),
            $connectionName
        );
    }

    /** @throws \Exception */
    public static function createFromBuilder(
        string $connectionString = null,
        ConnectionSettingsBuilder $builder = null,
        string $connectionName = ''
    ): SyncConnection {
        $connection = self::createAsyncFromBuilder(
            $connectionString,
            $builder,
            $connectionName
        );

        return new EventStoreSyncNodeConnection($connection);
    }

    /** @throws \Exception */
    public static function createFromSettings(
        string $connectionString = null,
        ConnectionSettings $settings = null,
        string $connectionName = ''
    ): SyncConnection {
        $connection = self::createAsyncFromSettings(
            $connectionString,
            $settings,
            $connectionName
        );

        return new EventStoreSyncNodeConnection($connection);
    }

    public static function createFromIpEndPoint(
        IpEndPoint $endPoint,
        ConnectionSettings $settings = null,
        string $connectionName = null
    ): SyncConnection {
        $connection = self::createAsyncFromIpEndPoint(
            $endPoint,
            $settings,
            $connectionName
        );

        return new EventStoreSyncNodeConnection($connection);
    }

    private static function createWithClusterDnsEndPointDiscoverer(
        ConnectionSettings $settings,
        string $connectionName = null
    ): AsyncConnection {
        $clusterSettings = new ClusterSettings(
            $settings->clusterDns(),
            $settings->maxDiscoverAttempts(),
            $settings->externalGossipPort(),
            $settings->gossipSeeds(),
            $settings->gossipTimeout(),
            $settings->preferRandomNode()
        );

        $endPointDiscoverer = new ClusterDnsEndPointDiscoverer(
            $settings->log(),
            $settings->clusterDns(),
            $settings->maxDiscoverAttempts(),
            $settings->externalGossipPort(),
            $settings->gossipSeeds(),
            $settings->gossipTimeout(),
            $settings->preferRandomNode()
        );

        return new EventStoreAsyncNodeConnection($settings, $clusterSettings, $endPointDiscoverer, $connectionName);
    }

    private static function createWithSingleEndpointDiscoverer(
        string $connectionString,
        ConnectionSettings $settings,
        string $connectionName = null
    ): AsyncConnection {
        return new EventStoreAsyncNodeConnection(
            $settings,
            null,
            new SingleEndpointDiscoverer($connectionString, $settings->useSslConnection()),
            $connectionName
        );
    }

    private static function parseUri(string $connectionString): array
    {
        $parts = \parse_url($connectionString);

        if (false === $parts) {
            throw new InvalidArgumentException(
                'The source URI string appears to be malformed'
            );
        }

        $scheme = isset($parts['scheme']) ? self::filterScheme($parts['scheme']) : '';
        $host = isset($parts['host']) ? \strtolower($parts['host']) : '';
        $port = isset($parts['port']) ? (int) $parts['port'] : Consts::TcpPortDefault;
        $user = isset($parts['user']) ? self::filterUserInfoPart($parts['user']) : '';
        $pass = $parts['pass'] ?? '';

        return [
            $scheme,
            $host,
            $port,
            $user,
            $pass,
        ];
    }

    private static function filterScheme(string $scheme): string
    {
        return \preg_replace('#:(//)?$#', '', \strtolower($scheme));
    }

    private static function filterUserInfoPart(string $part): string
    {
        // Note the addition of `%` to initial charset; this allows `|` portion
        // to match and thus prevent double-encoding.
        return \preg_replace_callback(
            '/(?:[^%' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . ']+|%(?![A-Fa-f0-9]{2}))/u',
            function (array $matches): string {
                return \rawurlencode($matches[0]);
            },
            $part
        );
    }
}
