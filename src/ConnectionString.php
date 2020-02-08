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

use Prooph\EventStore\EndPoint;
use Prooph\EventStore\Exception\InvalidArgumentException;
use Prooph\EventStore\UserCredentials;
use ReflectionObject;

class ConnectionString
{
    private static array $allowedValues = [
        'verboselogging' => 'bool',
        'maxqueuesize' => 'int',
        'maxconcurrentitems' => 'int',
        'maxretries' => 'int',
        'maxreconnections' => 'int',
        'requiremaster' => 'bool',
        'reconnectiondelay' => 'int',
        'operationtimeout' => 'int',
        'operationtimeoutcheckperiod' => 'int',
        'defaultusercredentials' => UserCredentials::class,
        'usesslconnection' => 'bool',
        'targethost' => 'string',
        'validateserver' => 'bool',
        'failonnoserverresponse' => 'bool',
        'heartbeatinterval' => 'int',
        'heartbeattimeout' => 'int',
        'clusterdns' => 'string',
        'maxdiscoverattempts' => 'int',
        'externalgossipport' => 'int',
        'gossipseeds' => GossipSeed::class,
        'gossiptimeout' => 'int',
        'preferrandomNode' => 'bool',
        'clientconnectiontimeout' => 'int',
    ];

    public static function getConnectionSettings(
        string $connectionString,
        ?ConnectionSettings $settings = null
    ): ConnectionSettings {
        $settings ??= ConnectionSettings::default();
        $reflection = new ReflectionObject($settings);
        $properties = $reflection->getProperties();
        $values = self::getParts($connectionString);

        foreach ($values as $value) {
            [$key, $value] = \explode('=', $value);
            $key = \strtolower($key);

            if ('connectto' === $key) {
                continue;
            }

            if (! \array_key_exists($key, self::$allowedValues)) {
                throw new InvalidArgumentException(\sprintf(
                    'Key %s is not an allowed key in %s',
                    $key,
                    self::class
                ));
            }

            $type = self::$allowedValues[$key];

            switch ($type) {
                case 'bool':
                    $filteredValue = \filter_var($value, \FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'int':
                    $filteredValue = \filter_var($value, \FILTER_VALIDATE_INT);

                    if (false === $filteredValue) {
                        throw new InvalidArgumentException(\sprintf(
                            'Expected type for key %s is %s, but %s given',
                            $key,
                            $type,
                            $value
                        ));
                    }
                    break;
                case 'string':
                    $filteredValue = $value;
                    break;
                case UserCredentials::class:
                    $exploded = \explode(':', $value);

                    if (\count($exploded) !== 2) {
                        throw new InvalidArgumentException(\sprintf(
                            'Expected user credentials in format user:pass, %s given',
                            $value
                        ));
                    }

                    $filteredValue = new UserCredentials($exploded[0], $exploded[1]);
                    break;
                case GossipSeed::class:
                    $gossipSeeds = [];

                    foreach (\explode(',', $value) as $v) {
                        $exploded = \explode(':', $v);

                        if (\count($exploded) !== 2) {
                            throw new InvalidArgumentException(\sprintf(
                                'Expected user credentials in format user:pass, %s given',
                                $value
                            ));
                        }

                        $host = $exploded[0];
                        $port = \filter_var($exploded[1], \FILTER_VALIDATE_INT);

                        if (false === $port) {
                            throw new InvalidArgumentException(\sprintf(
                                'Expected type for port of gossip seed is int, but %s given',
                                $exploded[1]
                            ));
                        }

                        $gossipSeeds[] = new GossipSeed(new EndPoint($host, $port));
                    }

                    if (empty($gossipSeeds)) {
                        throw new InvalidArgumentException(\sprintf(
                            'No gossip seeds specified in connection string'
                        ));
                    }

                    $filteredValue = $gossipSeeds;
                    break;
            }

            foreach ($properties as $property) {
                if (\strtolower($property->getName()) === $key) {
                    $property->setAccessible(true);
                    $property->setValue($settings, $filteredValue);
                    break;
                }
            }
        }

        return $settings;
    }

    public static function getUriFromConnectionString(string $connectionString): ?Uri
    {
        $values = self::getParts($connectionString);

        foreach ($values as $value) {
            [$key, $value] = \explode('=', $value);

            if (\strtolower($key) === 'connectto') {
                return Uri::fromString($value);
            }
        }

        return null;
    }

    /**
     * @param string $connectionString
     *
     * @return string[]
     */
    private static function getParts(string $connectionString): array
    {
        return \explode(';', \str_replace(' ', '', $connectionString));
    }
}
