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
use Prooph\EventStoreClient\Exception\InvalidOperationException;
use Prooph\EventStoreClient\Internal\AbstractEventStorePersistentSubscription;
use Prooph\EventStoreClient\Internal\ResolvedEvent;
use Prooph\EventStoreClient\Messages\ClientMessages\CreatePersistentSubscription;
use Throwable;

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

    $connection->connectToPersistentSubscriptionAsync(
        'foo-bar',
        'test-persistent-subscription',
        new class() implements EventAppearedOnPersistentSubscription {
            public function __invoke(
                AbstractEventStorePersistentSubscription $subscription,
                ResolvedEvent $resolvedEvent): Promise
            {
                echo 'incoming event: ' . $resolvedEvent->originalEventNumber() . '@' . $resolvedEvent->originalStreamName() . PHP_EOL;
                echo 'data: ' . $resolvedEvent->originalEvent()->data() . PHP_EOL;

                return new Success();
            }
        },
        new class() implements SubscriptionDroppedOnPersistentSubscription {
            public function __invoke(
                AbstractEventStorePersistentSubscription $subscription,
                SubscriptionDropReason $reason,
                Throwable $exception = null): void
            {
                echo 'dropped with reason: ' . $reason->name() . PHP_EOL;

                if ($exception) {
                    echo 'ex: ' . $exception->getMessage() . PHP_EOL;
                }
            }
        },
        10,
        true,
        new UserCredentials('admin', 'changeit')
    );
});
