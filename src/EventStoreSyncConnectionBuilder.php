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

class EventStoreSyncConnectionBuilder
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

    private const TCP_PORT_DEFAULT = 1113;

    /** @throws \Exception */
    public static function createFromBuilder(
        ?string $connectionString = null,
        ?ConnectionSettingsBuilder $builder = null,
        string $connectionName = ''
    ): SyncConnection {
        $builder = $builder ?? new ConnectionSettingsBuilder();

        return self::createFromSettings($connectionString, $builder->build(), $connectionName);
    }

    /** @throws \Exception */
    public static function createFromSettings(
        ?string $connectionString = null,
        ?ConnectionSettings $settings = null,
        string $connectionName = ''
    ): SyncConnection {
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
                    $settings->log(),
                    $settings->verboseLogging(),
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
                    $settings->clusterDns(),
                    $settings->maxDiscoverAttempts(),
                    $settings->externalGossipPort(),
                    $settings->gossipSeeds(),
                    $settings->gossipTimeout(),
                    $settings->preferRandomNode(),
                    $settings->clientConnectionTimeout()
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

    public static function createFromBuilderWithEndPoint(
        EndPoint $endPoint,
        ?ConnectionSettingsBuilder $builder = null,
        ?string $connectionName = null
    ): SyncConnection {
        $builder = $builder ?? new ConnectionSettingsBuilder();

        return self::createFromSettingsWithEndPoint($endPoint, $builder->build(), $connectionName);
    }

    public static function createFromSettingsWithEndPoint(
        EndPoint $endPoint,
        ?ConnectionSettings $settings = null,
        ?string $connectionName = null
    ): SyncConnection {
        $settings = $settings ?? ConnectionSettings::default();

        return new EventStoreSyncNodeConnection(
            $settings,
            null,
            new StaticEndPointDiscoverer($endPoint, $settings->useSslConnection()),
            $connectionName
        );
    }

    private static function createWithClusterDnsEndPointDiscoverer(
        ConnectionSettings $settings,
        ?string $connectionName = null
    ): SyncConnection {
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

        return new EventStoreSyncNodeConnection($settings, $clusterSettings, $endPointDiscoverer, $connectionName);
    }

    private static function createWithSingleEndpointDiscoverer(
        string $connectionString,
        ConnectionSettings $settings,
        ?string $connectionName = null
    ): SyncConnection {
        return new EventStoreSyncNodeConnection(
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
        $port = isset($parts['port']) ? (int) $parts['port'] : self::TCP_PORT_DEFAULT;
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
