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

namespace ProophTest\EventStoreClient;

use Amp\Deferred;
use Amp\Promise;
use Amp\Success;
use Amp\TimeoutException;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\ExpectedVersion;
use ProophTest\EventStoreClient\Helper\Connection;
use ProophTest\EventStoreClient\Helper\TestEvent;
use function Amp\call;
use function Amp\Promise\timeout;

class subscribe_should extends TestCase
{
    private const Timeout = 10000;

    /** @test */
    public function be_able_to_subscribe_to_non_existing_stream_and_then_catch_new_event(): void
    {
        Promise\wait(call(function () {
            $stream = 'subscribe_should_be_able_to_subscribe_to_non_existing_stream_and_then_catch_created_event';

            $appeared = new Deferred();

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            yield $connection->subscribeToStreamAsync(
                $stream,
                false,
                function () use ($appeared): Promise {
                    $appeared->resolve(true);

                    return new Success();
                }
            );

            yield $connection->appendToStreamAsync($stream, ExpectedVersion::EmptyStream, [TestEvent::new()]);

            try {
                $result = yield timeout($appeared->promise(), self::Timeout);
                $this->assertTrue($result);
            } catch (TimeoutException $e) {
                $this->fail('Appeared countdown event timed out');

                return;
            }

            $connection->close();
        }));
    }
}
