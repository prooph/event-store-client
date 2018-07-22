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
use Amp\Promise;
use Amp\Success;
use Prooph\EventStoreClient\Internal\StopWatch;
use Prooph\EventStoreClient\Internal\VolatileEventStoreSubscription;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(function () {
    $connection = EventStoreConnectionBuilder::createAsyncFromIpEndPoint(
        new IpEndPoint('eventstore', 1113)
    );

    $connection->onConnected(function (): void {
        echo 'connected' . PHP_EOL;
    });

    $connection->onClosed(function (): void {
        echo 'connection closed' . PHP_EOL;
    });

    yield $connection->connectAsync();

    $stopWatch = StopWatch::startNew();
    $i = 0;

    // @todo: there may be a bug somewhere, couldn't locate it yet. The server does not confirm the subscription yet !!
    $subscription = yield $connection->subscribeToAllAsync(
        true,
        function (VolatileEventStoreSubscription $subscription, ResolvedEvent $event) use ($stopWatch, &$i): Promise {
            echo 'incoming event: ' . $event->originalEventNumber() . '@' . $event->originalStreamName() . PHP_EOL;
            echo 'data: ' . $event->originalEvent()->data() . PHP_EOL;
            echo 'no: ' . ++$i . ', elapsed: ' . $stopWatch->elapsed() . PHP_EOL;

            return new Success();
        },
        function (VolatileEventStoreSubscription $subscription, SubscriptionDropReason $reason, \Throwable $exception): void {
            echo 'dropped with reason: ' . $reason->name() . PHP_EOL;
            echo 'ex: ' . $exception->getMessage() . PHP_EOL;
        },
        new UserCredentials('admin', 'changeit')
    );

    /** @var VolatileEventStoreSubscription $subscription */
    echo 'last event number: ' . $subscription->lastCommitPosition() . PHP_EOL;
});
