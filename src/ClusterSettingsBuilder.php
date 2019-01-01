<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2019 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient;

class ClusterSettingsBuilder
{
    /**
     * Sets the client to discover nodes using a DNS name and a well-known port.
     */
    public function discoverClusterViaDns(): DnsClusterSettingsBuilder
    {
        return new DnsClusterSettingsBuilder();
    }

    /**
     * Sets the client to discover cluster nodes by specifying the IP endpoints of
     * one or more of the nodes.
     */
    public function discoverClusterViaGossipSeeds(): GossipSeedClusterSettingsBuilder
    {
        return new GossipSeedClusterSettingsBuilder();
    }
}
