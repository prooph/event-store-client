<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2019 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2019 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use function Amp\call;
use function Amp\Promise\wait;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\DeleteResult;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\ExpectedVersion;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;

class deleting_stream extends TestCase
{
    /**
     * @test
     * @throws Throwable
     * @doesNotPerformAssertions
     */
    public function which_doesnt_exists_should_success_when_passed_empty_stream_expected_version(): void
    {
        wait(call(function () {
            $stream = 'which_already_exists_should_success_when_passed_empty_stream_expected_version';

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            yield $connection->deleteStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, true);

            $connection->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     * @doesNotPerformAssertions
     */
    public function which_doesnt_exists_should_success_when_passed_any_for_expected_version(): void
    {
        wait(call(function () {
            $stream = 'which_already_exists_should_success_when_passed_any_for_expected_version';

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            yield $connection->deleteStreamAsync($stream, ExpectedVersion::ANY, true);

            $connection->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function with_invalid_expected_version_should_fail(): void
    {
        wait(call(function () {
            $stream = 'with_invalid_expected_version_should_fail';

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            try {
                $this->expectException(WrongExpectedVersion::class);
                yield $connection->deleteStreamAsync($stream, 1, true);
            } finally {
                $connection->close();
            }
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_return_log_position_when_writing(): void
    {
        wait(call(function () {
            $stream = 'delete_should_return_log_position_when_writing';

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            yield $connection->appendToStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, [TestEvent::newTestEvent()]);

            $delete = yield $connection->deleteStreamAsync($stream, 0, true);
            \assert($delete instanceof DeleteResult);

            $this->assertGreaterThan(0, $delete->logPosition()->preparePosition());
            $this->assertGreaterThan(0, $delete->logPosition()->commitPosition());

            $connection->close();
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function which_was_already_deleted_should_fail(): void
    {
        wait(call(function () {
            $stream = 'which_was_allready_deleted_should_fail';

            $connection = TestConnection::create();

            yield $connection->connectAsync();

            yield $connection->deleteStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, true);

            try {
                $this->expectException(StreamDeleted::class);
                yield $connection->deleteStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, true);
            } finally {
                $connection->close();
            }
        }));
    }
}
