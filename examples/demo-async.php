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

use Amp\Loop;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(function () {
    $connection = EventStoreAsyncConnectionBuilder::createFromIpEndPoint(
        new IpEndPoint('eventstore', 1113)
    );

    $connection->onConnected(function (): void {
        echo 'connected' . PHP_EOL;
    });

    $connection->onClosed(function (): void {
        echo 'connection closed' . PHP_EOL;
    });

    yield $connection->connectAsync();

    $slice = yield $connection->readStreamEventsForwardAsync(
        'foo-bar',
        10,
        2,
        true
    );

    \var_dump($slice);

    $slice = yield $connection->readStreamEventsBackwardAsync(
        'foo-bar',
        10,
        2,
        true
    );

    \var_dump($slice);

    $event = yield $connection->readEventAsync('foo-bar', 2, true);

    \var_dump($event);

    $m = yield $connection->getStreamMetadataAsync('foo-bar');

    \var_dump($m);

    $r = yield $connection->setStreamMetadataAsync('foo-bar', ExpectedVersion::ANY, new StreamMetadata(
        null, null, null, null, null, [
            'foo' => 'bar',
        ]
    ));

    \var_dump($r);

    $m = yield $connection->getStreamMetadataAsync('foo-bar');

    \var_dump($m);

    $wr = yield $connection->appendToStreamAsync('foo-bar', ExpectedVersion::ANY, [
        new EventData(EventId::generate(), 'test-type', false, 'jfkhksdfhsds', 'meta'),
        new EventData(EventId::generate(), 'test-type2', false, 'kldjfls', 'meta'),
        new EventData(EventId::generate(), 'test-type3', false, 'aaa', 'meta'),
        new EventData(EventId::generate(), 'test-type4', false, 'bbb', 'meta'),
    ]);

    \var_dump($wr);

    $ae = yield $connection->readAllEventsForwardAsync(Position::start(), 2, false, new UserCredentials(
        'admin',
        'changeit'
    ));

    \var_dump($ae);

    $aeb = yield $connection->readAllEventsBackwardAsync(Position::end(), 2, false, new UserCredentials(
        'admin',
        'changeit'
    ));

    \var_dump($aeb);

    $connection->close();
});
