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

use Amp\Loop;
use Amp\Promise;
use Amp\Success;
use Prooph\EventStore\AsyncEventStoreCatchUpSubscription;
use Prooph\EventStore\EndPoint;
use Prooph\EventStore\EventAppearedOnAsyncSubscription;
use Prooph\EventStore\EventStoreSubscription;
use Prooph\EventStore\LiveProcessingStartedOnAsyncCatchUpSubscription;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SubscriptionDropped;
use Prooph\EventStore\SubscriptionDropReason;
use Throwable;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(function () {
    $builder = new ConnectionSettingsBuilder();
    $builder->enableVerboseLogging();
    $builder->useConsoleLogger();

    $connection = EventStoreConnectionFactory::createFromEndPoint(
        new EndPoint('eventstore', 1113),
        $builder->build()
    );

    $connection->onConnected(function (): void {
        echo 'connected' . PHP_EOL;
    });

    $connection->onClosed(function (): void {
        echo 'connection closed' . PHP_EOL;
    });

    yield $connection->connectAsync();

    yield $connection->subscribeToStreamFromAsync(
        'foo-bar',
        null,
        CatchUpSubscriptionSettings::default(),
        new class() implements EventAppearedOnAsyncSubscription {
            public function __invoke(
                EventStoreSubscription $subscription,
                ResolvedEvent $resolvedEvent): Promise
            {
                echo 'incoming event: ' . $resolvedEvent->originalEventNumber() . '@' . $resolvedEvent->originalStreamName() . PHP_EOL;
                echo 'data: ' . $resolvedEvent->originalEvent()->data() . PHP_EOL;

                return new Success();
            }
        },
        new class() implements LiveProcessingStartedOnAsyncCatchUpSubscription {
            public function __invoke(AsyncEventStoreCatchUpSubscription $subscription): void
            {
                echo 'liveProcessingStarted on ' . $subscription->streamId() . PHP_EOL;
            }
        },
        new class() implements SubscriptionDropped {
            public function __invoke(
                EventStoreSubscription $subscription,
                SubscriptionDropReason $reason,
                ?Throwable $exception = null
            ): void {
                echo 'dropped with reason: ' . $reason->name() . PHP_EOL;

                if ($exception) {
                    echo 'ex: ' . $exception->getMessage() . PHP_EOL;
                }
            }
        }
    );
});
