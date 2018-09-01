<?php
/**
 * This file is part of the prooph/event-store-client.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient;

require __DIR__ . '/../vendor/autoload.php';

$connection = EventStoreAsyncConnectionBuilder::createFromSettingsWithIpEndPoint(
    new EndPoint('eventstore', 1113)
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
    100,
    20,
    true
);

\var_dump($slice);

$slice = $connection->readStreamEventsBackward(
    'foo-bar',
    10,
    2,
    true
);

$event = $connection->readEvent('foo-bar', 2, true);

\var_dump($event);

$m = $connection->getStreamMetadata('foo-bar');

\var_dump($m);

$wr = $connection->appendToStream('foo-bar', ExpectedVersion::ANY, [
    new EventData(EventId::generate(), 'test-type', false, 'jfkhksdfhsds', 'meta'),
    new EventData(EventId::generate(), 'test-type2', false, 'kldjfls', 'meta'),
    new EventData(EventId::generate(), 'test-type3', false, 'aaa', 'meta'),
    new EventData(EventId::generate(), 'test-type4', false, 'bbb', 'meta'),
]);

\var_dump($wr);

$connection->close();
