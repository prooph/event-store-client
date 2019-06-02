<?php
/**
 * This file is part of the prooph/event-store-client.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2019 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use RuntimeException;

require_once __DIR__ . '/../vendor/autoload.php';

if (! \extension_loaded('protobuf')) {
    throw new RuntimeException('ext-protobuf is missing');
}
