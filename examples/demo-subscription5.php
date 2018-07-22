<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Amp\Loop;
use Amp\Promise;
use Amp\Success;
use Prooph\EventStoreClient\Exception\InvalidOperationException;
use Prooph\EventStoreClient\Internal\EventStorePersistentSubscription;
use Prooph\EventStoreClient\Internal\StopWatch;
use Prooph\EventStoreClient\Messages\ClientMessages\CreatePersistentSubscription;

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

    try {
        $result = yield $connection->deletePersistentSubscriptionAsync(
            'foo-bar',
            'test-persistent-subscription',
            new UserCredentials('admin', 'changeit')
        );
        \var_dump($result);
    } catch (InvalidOperationException $exception) {
        echo 'no such subscription exists (yet)' . PHP_EOL;
    }

    /** @var CreatePersistentSubscription $result */
    $result = yield $connection->createPersistentSubscriptionAsync(
        'foo-bar',
        'test-persistent-subscription',
        PersistentSubscriptionSettings::default(),
        new UserCredentials('admin', 'changeit')
    );

    \var_dump($result);

    $stopWatch = StopWatch::startNew();
    $i = 0;

    $subscription = $connection->connectToPersistentSubscription(
        'foo-bar',
        'test-persistent-subscription',
        function (EventStorePersistentSubscription $subscription, ResolvedEvent $event, int $retry) use ($stopWatch, &$i): Promise {
            echo 'incoming event: ' . $event->originalEventNumber() . '@' . $event->originalStreamName() . PHP_EOL;
            echo 'data: ' . $event->originalEvent()->data() . PHP_EOL;
            echo 'retry: ' . $retry . PHP_EOL;
            echo 'no: ' . ++$i . ', elapsed: ' . $stopWatch->elapsed() . PHP_EOL;

            return new Success('tadataa');
        },
        function () {
            echo 'dropped' . PHP_EOL;
        },
        10,
        true,
        new UserCredentials('admin', 'changeit')
    );

    /** @var EventStorePersistentSubscription $subscription */
    $subscription = yield $subscription->start();
});
