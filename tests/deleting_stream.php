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

use Amp\Loop;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\DeleteResult;
use Prooph\EventStoreClient\Exception\StreamDeletedException;
use Prooph\EventStoreClient\Exception\WrongExpectedVersionException;
use Prooph\EventStoreClient\ExpectedVersion;
use ProophTest\EventStoreClient\Helper\Connection;
use ProophTest\EventStoreClient\Helper\TestEvent;

class deletingstream extends TestCase
{
    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function which_doesnt_exists_should_success_when_passed_empty_stream_expected_version(): void
    {
        Loop::run(function () {
            $stream = 'which_already_exists_should_success_when_passed_empty_stream_expected_version';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            yield $connection->deleteStreamAsync($stream, ExpectedVersion::EmptyStream, true);

            $connection->close();
        });
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function which_doesnt_exists_should_success_when_passed_any_for_expected_version(): void
    {
        Loop::run(function () {
            $stream = 'which_already_exists_should_success_when_passed_any_for_expected_version';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            yield $connection->deleteStreamAsync($stream, ExpectedVersion::Any, true);

            $connection->close();
        });
    }

    /** @test */
    public function with_invalid_expected_version_should_fail(): void
    {
        Loop::run(function () {
            $stream = 'with_invalid_expected_version_should_fail';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            try {
                $this->expectException(WrongExpectedVersionException::class);
                yield $connection->deleteStreamAsync($stream, 1, true);
            } finally {
                $connection->close();
            }
        });
    }

    /** @test */
    public function should_return_log_position_when_writing(): void
    {
        Loop::run(function () {
            $stream = 'delete_should_return_log_position_when_writing';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            yield $connection->appendToStreamAsync($stream, ExpectedVersion::EmptyStream, [TestEvent::new()]);

            /** @var DeleteResult $delete */
            $delete = yield $connection->deleteStreamAsync($stream, 0, true);

            $this->assertGreaterThan(0, $delete->logPosition()->preparePosition());
            $this->assertGreaterThan(0, $delete->logPosition()->commitPosition());

            $connection->close();
        });
    }

    /** @test */
    public function which_was_already_deleted_should_fail(): void
    {
        Loop::run(function () {
            $stream = 'which_was_allready_deleted_should_fail';

            $connection = Connection::createAsync();

            yield $connection->connectAsync();

            yield $connection->deleteStreamAsync($stream, ExpectedVersion::EmptyStream, true);

            try {
                $this->expectException(StreamDeletedException::class);
                yield $connection->deleteStreamAsync($stream, ExpectedVersion::EmptyStream, true);
            } finally {
                $connection->close();
            }
        });
    }
}
