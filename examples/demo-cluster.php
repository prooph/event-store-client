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

namespace Prooph\EventStoreClient;

use Prooph\EventStore\EndPoint;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\Position;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\UserCredentials;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Start docker/local-3-node-cluster and run this script from host machine.
 * This is because the cluster advertises as 127.0.0.1, which does not resolve
 * to the event store in the PHP container, if you run it in Docker.
 */

$builder = new ConnectionSettingsBuilder();
$builder->setGossipSeedEndPoints([
    new EndPoint('localhost', 2113),
    new EndPoint('localhost', 2123),
    new EndPoint('localhost', 2133),
], false);

$connection = EventStoreConnectionFactory::createFromSettings(
    $builder->build(),
    'cluster-connection'
);

$connection->onConnected(function (): void {
    echo 'connected' . PHP_EOL;
});

$connection->onClosed(function (): void {
    echo 'connection closed' . PHP_EOL;
});

$connection->connect();

$slice = $connection->readStreamEventsForward(
    'foo-bar',
    10,
    2,
    true
);

\var_dump($slice);

$slice = $connection->readStreamEventsBackward(
    'foo-bar',
    10,
    2,
    true
);

\var_dump($slice);

$event = $connection->readEvent('foo-bar', 2, true);

\var_dump($event);

$m = $connection->getStreamMetadata('foo-bar');

\var_dump($m);

$r = $connection->setStreamMetadata('foo-bar', ExpectedVersion::Any, new StreamMetadata(
    null,
    null,
    null,
    null,
    null,
    [
        'foo' => 'bar',
    ]
));

\var_dump($r);

$m = $connection->getStreamMetadata('foo-bar');

\var_dump($m);

$wr = $connection->appendToStream('foo-bar', ExpectedVersion::Any, [
    new EventData(EventId::generate(), 'test-type', false, 'jfkhksdfhsds', 'meta'),
    new EventData(EventId::generate(), 'test-type2', false, 'kldjfls', 'meta'),
    new EventData(EventId::generate(), 'test-type3', false, 'aaa', 'meta'),
    new EventData(EventId::generate(), 'test-type4', false, 'bbb', 'meta'),
]);

\var_dump($wr);

$ae = $connection->readAllEventsForward(Position::start(), 2, false, new UserCredentials(
    'admin',
    'changeit'
));

\var_dump($ae);

$aeb = $connection->readAllEventsBackward(Position::end(), 2, false, new UserCredentials(
    'admin',
    'changeit'
));

\var_dump($aeb);

$connection->close();
