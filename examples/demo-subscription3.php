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
use Prooph\EventStoreClient\Internal\ResolvedEvent;
use Prooph\EventStoreClient\Internal\VolatileEventStoreSubscription;
use Throwable;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(function () {
    $connection = EventStoreAsyncConnectionBuilder::createFromIpEndPoint(
        new EndPoint('eventstore', 1113)
    );

    $connection->onConnected(function (): void {
        echo 'connected' . PHP_EOL;
    });

    $connection->onClosed(function (): void {
        echo 'connection closed' . PHP_EOL;
    });

    yield $connection->connectAsync();

    $subscription = yield $connection->subscribeToStreamAsync(
        'foo-bar',
        true,
        new class() implements EventAppearedOnSubscription {
            public function __invoke(
                EventStoreSubscription $subscription,
                ResolvedEvent $resolvedEvent): Promise
            {
                echo 'incoming event: ' . $resolvedEvent->originalEventNumber() . '@' . $resolvedEvent->originalStreamName() . PHP_EOL;
                echo 'data: ' . $resolvedEvent->originalEvent()->data() . PHP_EOL;

                return new Success();
            }
        },
        new class() implements SubscriptionDroppedOnSubscription {
            public function __invoke(
                EventStoreSubscription $subscription,
                SubscriptionDropReason $reason,
                Throwable $exception = null): void
            {
                echo 'dropped with reason: ' . $reason->name() . PHP_EOL;

                if ($exception) {
                    echo 'ex: ' . $exception->getMessage() . PHP_EOL;
                }
            }
        },
        new UserCredentials('admin', 'changeit')
    );

    /** @var VolatileEventStoreSubscription $subscription */
    echo 'last event number: ' . $subscription->lastEventNumber() . PHP_EOL;
});
